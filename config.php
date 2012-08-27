<?php

require_once($CFG->dirroot.'/theme/pdf/pdf_overridden_renderer_factory.php');

$THEME->name = 'pdf';
$THEME->parents = Array('base', 'standard');

$THEME->sheets = array('core');

//allow theme overriding
$THEME->rendererfactory = 'pdf_overridden_renderer_factory';

