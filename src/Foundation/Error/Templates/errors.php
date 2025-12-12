<?php 
/**
 * Luminova Framework error template.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */

use Luminova\Runtime;
use Luminova\Exceptions\ErrorCode;
use Luminova\Foundation\Error\Error;
use Luminova\Exceptions\LuminovaException;
use function Luminova\Funcs\display_path;

/**
 * @var LuminovaException|Throwable|null $error
 */
$name = null;
$message = $error->getMessage();

$lines = explode(PHP_EOL, $message);
$messages = explode(' called in ', $error->getMessage());

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/png" href="./favicon.png">
    <title>Error Occurred - <?= htmlspecialchars($name, ENT_QUOTES); ?></title>
    <style><?= preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__  . '/css/debug.css')) ?></style>
    <script>function toggle(id){ event.preventDefault(); var element=document.getElementById(id); if (element.style.display==="none"){ element.style.display="block";} else{ element.style.display="none";}} </script>
</head>
<body id="e_all">
    <div class="container text-center main-container">
        <h1 class="headline"><?= htmlspecialchars($name, ENT_QUOTES); ?> #<?= $error->getCode(); ?></h1>
        <?php if (!Runtime::isProduction()): ?>
            <div class="error-details">
                <h2>Error Details:</h2>
                <p class="entry"><?= Error::prettify($message); ?>. Thrown in: <?= htmlspecialchars(display_path($error->getFile()), ENT_QUOTES); ?>, Line: <?= $error->getLine(); ?></p>
                <?php if(isset($lines[2]) || isset($messages[1])): ?>
                    <p class="entry text-warning">Caller: <?= htmlspecialchars($lines[2] ?? $messages[1] ?? '', ENT_QUOTES); ?></p>
                <?php endif;?>
                <button class="button" type="button" onclick="return toggle('stack-tracer');">Stack tracer &#128269;</button>
                <a class="button" href="https://www.duckduckgo.com/?q=<?= $searchable; ?>" rel="noreferrer" target="_blank">Search Online &rarr;</a>
                <a class="button" href="https://luminova.ng/forum/search?q=<?= $searchable;?>" rel="noreferrer" target="_blank">Search Forum &#128270;</a>
                <a class="button" href="https://github.com/luminovang/luminova/issues/new?labels=bug&title=<?= $searchable;?>" rel="noreferrer" target="_blank">Open Issue &#128030;</a>
                <div id="stack-tracer" style="display:none;">
                    <?php 
                        if (Runtime::getConstantValue('SHOW_DEBUG_BACKTRACE') === true) : 
                            include_once __DIR__ . '/func/tracer.php';
                            __show_html_debug_tracer(
                                ($error instanceof LuminovaException) 
                                    ? $error->getBacktrace()
                                    : $error->getTrace(), 
                                array_slice($lines, 3),
                                $error->getFile()
                            );
                        endif;
                    ?>
                </div>
            </div>
        <?php else: ?>
            <h2>Origin is unreachable</h2>
            <p class="entry" style="margin-bottom: 20px;">An error is preventing website from loading properly.</p>
            <p class="entry" style="margin-bottom: 20px;">If you are the owner of this website, please check the error log for more information.</p>
        <?php endif; ?>
    </div>
    <div class="footer">
        <div class="container">
            <p>
                Displayed at <?= date('H:i:sA'); ?> &mdash;
                Luminova: <?=  \Luminova\Luminova::VERSION; ?> &mdash;
                Environment: <?= Runtime::getConstantValue('ENVIRONMENT', 'development'); ?>
            </p>
        </div>
    </div>
</body>
</html>