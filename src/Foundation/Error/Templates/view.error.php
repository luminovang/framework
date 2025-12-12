<?php 
/**
 * Luminova Framework vew error template.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
use Luminova\Exceptions\ErrorCode;
use Luminova\Foundation\Error\Error;
use Luminova\Exceptions\LuminovaException;
use function Luminova\Funcs\{href, display_path};
use const SHOW_DEBUG_BACKTRACE;

/**
 * @var LuminovaException|Throwable|null $error
 * @var string $title
 */
$name = null;
$message = $error->getMessage();

if($error instanceof LuminovaException){
    $name = $error->getName();
    $message = $error->getDescription();
}

$name ??= ErrorCode::getName($error->getCode());
$searchable = urlencode($message . ' PHP Luminova Framework');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">
    <meta http-equiv="Cache-Control" content="no-store, max-age=0, no-cache"/>
    <link rel="shortcut icon" type="image/png" href="<?= href('favicon.png');?>">
    <title>Template Error - <?= htmlspecialchars($name, ENT_QUOTES) ?></title>
    <style> <?= preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__ . '/css/debug.css')) ?> </style>
    <script>function toggle(id){ event.preventDefault(); var element=document.getElementById(id); if (element.style.display==="none"){ element.style.display="block";} else{ element.style.display="none";}} </script>
</head>
<body>
    <div class="header">
        <div class="container mt-4 <?= SHOW_DEBUG_BACKTRACE ?: 'main-container';?>">
            <h1>
                <?= htmlspecialchars($name, ENT_QUOTES); ?> #<?= $error->getCode(); ?>
                <span class="subtitle"><?= $title ? htmlspecialchars("($title)", ENT_QUOTES) : ''; ?></span>
            </h1>
            <p><?= nl2br(Error::prettify(rtrim($message, '.'))) ?>. Thrown in file: <?= display_path($error->getFile());?> on line: <?= $error->getLine();?></p>
            <p class="mt-2">
                <a class="button" href="https://www.duckduckgo.com/?q=<?= $searchable; ?>" rel="noreferrer" target="_blank">Search Online &rarr;</a>
                <a class="button" href="https://luminova.ng/forum/search?q=<?= $searchable;?>" rel="noreferrer" target="_blank">Search Forum &#128270;</a>
                <a class="button" href="https://github.com/luminovang/luminova/issues/new?labels=bug&title=<?= $searchable;?>" rel="noreferrer" target="_blank">Open Issue &#128030;</a>
            </p>
        </div>
    </div>

    <?php 
    if (SHOW_DEBUG_BACKTRACE) : 
        include_once __DIR__ . '/func/tracer.php';
        __show_html_debug_tracer(
            ($error instanceof LuminovaException) 
                ? $error->getBacktrace()
                : $error->getTrace(), 
            file: $error->getFile()
        );
    endif;
    ?>

    <div class="footer">
        <div class="container">
            <p>
                Displayed at <?= date('H:i:sA') ?> &mdash;
                Luminova: <?= \Luminova\Luminova::VERSION ?> &mdash;
                Environment: <?= ENVIRONMENT ?>
            </p>
        </div>
    </div>
</body>
</html>