<?php
/**
 * @author: Vitaliy Ofat <i@vitaliy-ofat.com>
 *
 * @var $grid Nayjest\Grids\Grid
 */
use Nayjest\Grids\Components\ExcelDownload;
$gridName = $grid->getConfig()->getName();
?>

<style>
    .button-visibility{
        visibility: hidden
    }
</style>

<script>
    $(document).ready(function() {
        var visibility = $.get("/check/<?=$gridName?>",function(){}).then(function(visibility){
            $(".button-visibility").css("visibility",visibility.visibility);
            if(visibility.visibility == "hidden"){
                myFunction();
            }
        });
        
    })
    function myFunction() {
        let interval = setInterval(function () {
            let visibility = $.get("/check/<?=$gridName?>",function(){}).then(function(visibility){
                $(".button-visibility").css("visibility",visibility.visibility);
                if(visibility.visibility == "visible"){
                    clearInterval(interval);
                }
            });
        }, 10000);
    }
</script>

<span class="button-visibility">
    <a
        href="/download/<?= $gridName ?>" target="_blank"
        class="btn btn-sm btn-default"
    >
        <span class="glyphicon glyphicon-download"></span>
        Excel Download
    </a>
</span>
