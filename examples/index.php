<html lang="en">
<head>
    <title>Examples</title>
</head>
<body>
<h1>Examples</h1>
<ul id="example-list">
    <?php
    foreach (new DirectoryIterator(__DIR__) as $file) {
        if ($file->isFile() && '.php' === mb_substr($file->getFilename(), -4) && !in_array($file->getFilename(), ['index.php', 'init.php'], true)) {
            echo '<li><a href="'.$file->getFilename().'">'.ucwords(str_replace('_', ' ', mb_substr($file->getFilename(), 0, -4))).'</a></li>';
        }
    }
    ?>
</ul>
</body>
</html>
