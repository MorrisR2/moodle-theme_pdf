<?php

//include all PHP files in the pdf_renderers directory
foreach(glob($CFG->dirroot . '/theme/pdf/pdf_renderers/*.php') as $renderer)
    include_once($renderer);

