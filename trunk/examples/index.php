<html>
<head>
</head>
<body>
    <h1>Examples</h1>
    <ul id="example-list">
    <?php

    foreach(new DirectoryIterator(realpath(dirname(__FILE__))) as $file) {
        if($file->isFile() && substr($file->getFilename(), -4) == '.php' && !in_array($file->getFilename(), array('index.php', 'init.php'))) {
            echo '<li><a href="' . $file->getFilename() . '">' . ucwords(str_replace('_', ' ', substr($file->getFilename(), 0, -4))) . '</a></li>';
        }
    }

    ?>
    </ul>
</body>
</html>
