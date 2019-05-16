<?php

namespace Nayjest\Grids\Components;

use Event;
use Illuminate\Pagination\Paginator;
use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Nayjest\Grids\Components\Base\RenderableComponent;
use Nayjest\Grids\Components\Base\RenderableRegistry;
use Nayjest\Grids\DataProvider;
use Nayjest\Grids\DataRow;
use Nayjest\Grids\FieldConfig;
use Nayjest\Grids\Grid;

/**
 * Class CsvExport
 *
 * The component provides control for exporting data to CSV.
 *
 * @author: Vitaliy Ofat <i@vitaliy-ofat.com>
 * @package Nayjest\Grids\Components
 */
class CsvExport extends RenderableComponent
{
    const NAME = 'csv_export';
    const INPUT_PARAM = 'csv';
    const CSV_DELIMITER = ';';
    const CSV_EXT = '.csv';
    const DEFAULT_ROWS_LIMIT = 5000;

    protected $template = '*.components.csv_export';
    protected $name = CsvExport::NAME;
    protected $render_section = RenderableRegistry::SECTION_END;
    protected $rows_limit = self::DEFAULT_ROWS_LIMIT;
    protected $ignored_columns = [];
    protected $is_hidden_columns_exported = false;

    /**
     * @var string
     */
    protected $output;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @param Grid $grid
     * @return null|void
     */
    public function initialize(Grid $grid)
    {
        parent::initialize($grid);
        Event::listen(Grid::EVENT_PREPARE, function (Grid $grid) {
            if ($this->grid !== $grid) {
                return;
            }
            if ($grid->getInputProcessor()->getValue(static::INPUT_PARAM, false)) {
                $this->renderCsv();
            }
        });
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setFileName($name)
    {
        $this->fileName = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName . static::CSV_EXT;
    }

    /**
     * @return int
     */
    public function getRowsLimit()
    {
        return $this->rows_limit;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function setRowsLimit($limit)
    {
        $this->rows_limit = $limit;
        return $this;
    }

    protected function setCsvHeaders(Response $response)
    {
        $response->header('Content-Type', 'application/csv');
        $response->header('Content-Disposition', 'attachment; filename=' . $this->getFileName());
        $response->header('Pragma', 'no-cache');
    }

    protected function resetPagination(DataProvider $provider)
    {
        if (version_compare(Application::VERSION, '5.0.0', '<')) {
            $provider->getPaginationFactory()->setPageName('page_unused');
        } else {
            Paginator::currentPageResolver(function () {
                return 1;
            });
        }
        $provider->setPageSize($this->getRowsLimit());
        $provider->setCurrentPage(1);
    }

    /**
     * @return string[]
     */
    public function getIgnoredColumns()
    {
        return $this->ignored_columns;
    }

    /**
     * @param string[] $ignoredColumns
     * @return $this
     */
    public function setIgnoredColumns(array $ignoredColumns)
    {
        $this->ignored_columns = $ignoredColumns;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isHiddenColumnsExported()
    {
        return $this->is_hidden_columns_exported;
    }

    /**
     * @param bool $isHiddenColumnsExported
     * @return $this
     */
    public function setHiddenColumnsExported($isHiddenColumnsExported)
    {
        $this->is_hidden_columns_exported = $isHiddenColumnsExported;
        return $this;
    }

    /**
     * @param FieldConfig $column
     * @return bool
     */
    protected function isColumnExported(FieldConfig $column)
    {
        return !in_array($column->getName(), $this->getIgnoredColumns())
            && ($this->isHiddenColumnsExported() || !$column->isHidden());
    }

    protected function getHeaderRow()
    {
        $output = [];
        foreach ($this->grid->getConfig()->getColumns() as $column) {
            if ($this->isColumnExported($column)) {
                $output[] = $this->escapeString($column->getLabel());
            }
        }
        return $output;
    }

    protected function renderCsv()
    {
        ini_set('max_execution_time', 6600);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'. $this->getFileName() .'"');
        header('Pragma: no-cache');

        $time = time();

        // Build array
        $exportData = [];
        /** @var $provider DataProvider */
        $provider = $this->grid->getConfig()->getDataProvider();

        $tmpFilePath = storage_path("app/csv-export-$this->fileName.csv");
        $tmpFile = fopen($tmpFilePath, 'w');
        fprintf($tmpFile, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($tmpFile, $this->getHeaderRow(), static::CSV_DELIMITER);

        $skip = 0;
        $limit = 1000;
        $count = $this->grid->getConfig()->getDataProvider()->getBuilder()->count();
        while ($skip < $count && $skip < $this->rows_limit) {
            $batch = $this->grid->getConfig()->getDataProvider()->getBuilder()->skip($skip)->limit($limit)->get();
            foreach ($batch as $item) {
                $output = [];
                foreach ($this->grid->getConfig()->getColumns() as $column) {
                    if ($this->isColumnExported($column)) {
                        $cb = $column->getCallback();
                        if ($cb) {
                            $row = new class {
                                protected $item = null;

                                public function setSrc($src) {
                                    $this->item = $src;
                                }

                                public function getSrc() {
                                    return $this->item;
                                }
                            };
                            $row->setSrc($item);
                            $output[] = $cb($item[$column->getName()], $row);
                        } else {
                            $output[] = $item[$column->getName()];
                        }
                    }
                }
                fputcsv($tmpFile, $output, static::CSV_DELIMITER);
            }
            $skip += $limit;
        }

        fclose($tmpFile);

        $file = @fopen($tmpFilePath, 'r');
        @ob_start();
        while (!feof($file)) {
            $buffer = fgets($file, 4096);
            echo $buffer;
            @ob_flush();
            @flush();
        }
        fclose($file);
        unlink($tmpFilePath);
        exit;
    }

    /**
     * @param string $str
     * @return string
     */
    protected function escapeString($str)
    {
        $str = html_entity_decode($str);
        $str = strip_tags($str);
        $str = str_replace('"', '\'', $str);
        $str = preg_replace('/\s+/', ' ', $str); # remove double spaces
        $str = trim($str);
        return $str;
    }

    /**
     * @param resource $file
     */
    protected function renderHeader($file)
    {
        $output = [];
        foreach ($this->grid->getConfig()->getColumns() as $column) {
            if (!$column->isHidden()) {
                $output[] = $this->escapeString($column->getLabel());
            }
        }
        fputcsv($file, $output, static::CSV_DELIMITER);
    }
}
