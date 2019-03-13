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

    .loading:after {
        content: ' .';
        animation: dots 1s steps(5, end) infinite;}

    @keyframes dots {
        0%, 20% {
            color: rgba(0,0,0,0);
            text-shadow:
                    .25em 0 0 rgba(0,0,0,0),
                    .5em 0 0 rgba(0,0,0,0);}
        40% {
            color: #797979;
            text-shadow:
                    .25em 0 0 rgba(0,0,0,0),
                    .5em 0 0 rgba(0,0,0,0);}
        60% {
            text-shadow:
                    .25em 0 0 #797979,
                    .5em 0 0 rgba(0,0,0,0);}
        80%, 100% {
            text-shadow:
                    .25em 0 0 #797979,
                    .5em 0 0 #797979;}}

</style>

<script>
    $(document).ready(function() {
        var visibility = $.get("/check/<?=$gridName?>",function(){}).then(function(visibility){
            $(".button-visibility").css("visibility",visibility.visibility);
            $(".loading").css("display",getInverseDisplay(visibility.visibility));
            if(visibility.visibility == "hidden"){
                myFunction();
            }
        });
        
    })
    function myFunction() {
        let interval = setInterval(function () {
            let visibility = $.get("/check/<?=$gridName?>",function(){}).then(function(visibility){
                $(".button-visibility").css("visibility",visibility.visibility);
                $(".loading").css("display",getInverseDisplay(visibility.visibility));
                if(visibility.visibility == "visible"){
                    clearInterval(interval);
                }
            });
        }, 10000);
    }

    function getInverseDisplay(visibility) {
        if (visibility == 'hidden') {
            return 'inline-block';
        }

        return 'none';
    }
</script>

<span class="loading">
    Preparing download
</span>

<span class="button-visibility">
    <a
        href="/download/<?= $gridName ?>" target="_blank"
        class="btn btn-sm btn-default"
    >
        <span class="glyphicon glyphicon-download"></span>
        Excel Download
    </a>
</span>
