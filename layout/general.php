<?php echo $OUTPUT->doctype() ?>
<html>
<head>
    <title><?php echo $PAGE->title ?></title>
    <link href='https://fonts.googleapis.com/css?family=Oswald:400,300|Droid+Sans:400,700' rel='stylesheet' type='text/css'>
    <?php
        echo html_writer::empty_tag('link', array(
            'href' => $CFG->wwwroot.'/theme/pdf/style/core.css',
            'rel' => 'stylesheet',
            'type' => 'text/css'
        ));
    ?>
</head>
<body id="<?php echo $PAGE->bodyid ?>" class="<?php echo $PAGE->bodyclasses ?>">
    <?php echo core_renderer::MAIN_CONTENT_TOKEN ?>
</body>
<html>


