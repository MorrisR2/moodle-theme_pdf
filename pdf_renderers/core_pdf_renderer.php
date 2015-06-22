<?php

require_once($CFG->dirroot . '/theme/pdf/lib/webkit_pdf.class.php');

/**
 * An extension of the core renderer, which uses the HTML2PDF library to produce PDFs instead of HTML.
 */
class core_pdf_renderer extends core_renderer
{
    const PAPER_LETTER = 'Letter';
	const PAPER_LEGAL = 'Legal';
	const PAPER_A3 = 'A3';
	const PAPER_A4 = 'A4';
	const PAPER_A5 = 'A5';

	const UNITS_ENGLISH = 'in';
	const UNITS_IMPERIAL = 'in';
	const UNITS_METRIC = 'mm';
	const UNITS_METRIC_CM = 'cm';
	const UNITS_POINT = 'pt';

    const ORIENTATION_PORTRAIT = 'P';
    const ORIENTATION_LANDSCAPE = 'L';

	//TODO: add more language codes
	const LANGUAGE_ENGLISH = 'en';
	const LANGUAGE_DEUTSCH = 'de';

    /**
     * If set to true, the output will not be purified with HTML purifier
     */
    public static $do_not_purify = false;

    /**
     * A local copy of the PDF renderer.
     */
    private $pdf;

   
    /**
     * Creates a new HTML2PDF object. 

     * Can be overridden to create renderers with non-default options.
     */
    protected static function create_new_pdf_writer($orientation=self::ORIENTATION_PORTRAIT, $paper_size=self::PAPER_LETTER, $language=self::LANGUAGE_ENGLISH)
    {
        $pdf = new webkit_pdf();
        $pdf->set_orientation($orientation);
        $pdf->set_page_size($paper_size);
        return $pdf;
    }


     
    public function header()
    {
        global $PAGE, $CFG;

        //start outut buffering, which we'll use to build the content for our PDF
        ob_start();

    }

    public function footer()
    {
        global $CFG;

        //note the lack of the HTML, HEAD, and BODY tags- HTML2PDF doesn't support the latter, and runs better without the former

        //terminate output buffering, and retrieve the entire contents of the page to be rendered
        $html = ob_get_clean();
        
        
        //debug flag; forces the renderer to skip PDF mode
        $no_pdf = optional_param('do_not_render', 0, PARAM_INT);

        //if we have the debug "do not render" flag, send the raw HTML
        if($no_pdf)
        {
            echo self::pdf_preprocess($html, false);
        }
        else
        {  
            //if we're instructed to, turn off contenttype
            if(optional_param('set_contenttype', 1, PARAM_INT) && !headers_sent())  
                header('Content-Type: application/pdf');

            //and display the PDF's content
            self::output_pdf($html, false, 'printable.pdf');
        }

    }

    public static function generate_pdf_header()
    {
        global $CFG;

        //inline the core PDF CSS 
        return html_writer::tag('style', file_get_contents($CFG->dirroot.'/theme/pdf/style/core.css'), array('type' => 'text/css'));
    }

    public static function pdf_preprocess($html, $rewrite_links=true)
    {
        global $CFG;

        //ensure the HTML code is well-formed, as part of it comes from the instructors
        if(!self::$do_not_purify)
            $html = self::clean_with_htmlpurify($html);

        //handle special characters
        $html = self::special_chars($html);

        //replace HTML-entity greek letters with PDF-font greek letters
        $html = self::greek_letters($html);

        //rewrite pluginfile and similar links, if desired
        if($rewrite_links)
            $html = self::rewrite_links($html);

        $html = str_replace('src="/', 'src="file:///' . $CFG->dirroot . '/', $html);
        //TODO: replace with preg match or better HTML parser
        $html = str_replace('\'courier new\', courier, monospace;', 'courier;', $html);

        return $html;
    }

    /**
     * TODO: Find a better way to do this, if one exists. Perhaps a renderer somewhere processes PLUGINFILE-style links?
     */
    public static function rewrite_links($html)
    {
        global $CFG;

        //escape the wwwroot location, so it can be used in a regular expression
        $root_url = preg_quote($CFG->wwwroot);

        //attempt to replace each pluginfile link with a
        $after = preg_replace_callback('#img(.*?) src\="'.$root_url.'/pluginfile\.php/([0-9]+)/([a-z_]+)/([a-z]+)/?([0-9]*)/?([0-9]*)/?([0-9]*)/([^"]+)#', array('core_pdf_renderer', 'rewrite_link_callback'), $html, -1);


        //if no error occurred, return the HTML string, with links replaced
        if($after)
            return $after;

        //otherwise, return the unmodified original
        else
            return $html;
    }

    public static function rewrite_link_callback($matches)
    {
        global $DB, $CFG;

        //matches array
        //0: original URI
        //1: any additional HTML fields between IMG and SRC, i.e. <img ____ src="
        //2: calling coursemodule ID
        //3: component
        //4: filearea
        //5: QUBA id (attempt uniqueid)
        //6: uploader's userid
        //7: question ID / itemid
        //8: filename

        //get a reference to the file in quesiton, if it exists
        $file = $DB->get_record('files', array('component' => $matches[3], 'filearea' => $matches[4], 'contextid' => $matches[2], 'itemid' => $matches[7], 'filename' => urldecode(html_entity_decode($matches[8]))));

        //if we couldn't find the given file, then return its untranslated filename
        if(!$file)
            return $matches[0];

        //calculate the main and subdir of the filename
        $main_dir = substr($file->contenthash, 0, 2);
        $sub_dir = substr($file->contenthash, 2, 2);

        //and return the file's location on disk
        return 'img '.$matches[1].' src="file:///'.$CFG->dataroot.'/filedir/'.$main_dir.'/'.$sub_dir.'/'.$file->contenthash;
    }


    public static function clean_with_htmlpurify($html)
    {
        //create a new HTMLPurify configuration object
        $config = HTMLPurifier_Config::createDefault();

        //allow the non-HTML tages which are provided by the PDF rendering engine:
        
        //get a reference to the HTML metalanguage which we use to clean up our HTML
        $metalanguage = $config->getHTMLDefinition(true);

        //and add our elements to it:
        $metalanguage->addElement('page_header', 'Block', 'Flow', 'Common', array());
        $metalanguage->addElement('page_footer', 'Block', 'Flow', 'Common', array());
        $metalanguage->addElement('page', 'Block', 'Flow', 'Common',
            array
            (
                'backtop' => 'Text',
                'backbottom' => 'Text',
                'backleft' => 'Text',
                'backright' => 'Text',
            ));
        $metalanguage->addElement('qrcode', 'Block', 'Flow', 'Common',
            array
            (
                'value' => 'Text',
                'ec' => 'Text',
                'style' => 'Text',
            )); 
        $metalanguage->addElement('barcode', 'Block', 'Flow', 'Common',
            array
            (
                'value' => 'Text',
                'type' => 'Text',
                'label' => 'Text',
                'style' => 'Text',
            ));
        $metalanguage->addElement('bookmark', 'Block', 'Flow', 'Common',
            array
            (
                'title' => 'Text',
                'level' => 'Number',
            ));


        //and create a new HTML purifier object
        $purifier = new HTMLPurifier($config);

        //and return the purified HTML
        return $purifier->purify($html);
    }


    public static function special_chars($item)
	{
        return mb_convert_encoding($item, 'HTML-ENTITIES', 'UTF-8'); 
	}

	public static function greek_letters($item)
	{
		$greek_letters = 
			array
			(
				//Each of the following Greek HTML entities has a counterpart in the core font 'symbol'.
				'&Alpha;' => 'A',
			   	'&Beta;' => 'B',
				'&Gamma;' => 'C',
				'&Delta;' => 'D',
				'&Epsilon;' => 'E',
				'&Zeta;' => 'Z',
				'&Eta;' => 'E',
				'&Theta;' => 'Q',
				'&Iota;' => 'I',
				'&Kappa;' => 'K',
				'&Lambda;' => 'L',
				'&Mu;' => 'M',
				'&Nu;' => 'N',
				'&Xi;' => 'X',
				'&Omicron;' => 'O',
				'&Pi;' => 'P',
				'&Rho;' => 'R',
				'&Sigma;' => 'S',
				'&Tau;' => 'T',
				'&Upsilon;' => 'U',
				'&Phi;' => 'F',
				'&Chi;' => 'X',
				'&Psi;' => 'Y',
				'&Omega;' => 'W',
				'&alpha;' => 'a',
				'&beta;' => 'b',
				'&gamma;' => 'c',
				'&delta;' => 'd',
				'&epsilon;' => 'e',
				'&zeta;' => 'z',
				'&eta;' => 'e',
				'&theta;' => 'q',
				'&iota;' => 'i',
				'&kappa;' => 'k',
                '&lambda;' => 'l',
                '&#188;' => 'm',
				'&mu;' => 'm',
				'&nu;' => 'n',
				'&xi;' => 'x',
				'&omicron;' => 'o',
				'&pi;' => 'p',
				'&rho;' => 'r',
				'&sigma;' => 's',
				'&tau;' => 't',
				'&upsilon;' => 'u',
				'&phi;' => 'f',
				'&chi;' => 'x',
				'&psi;' => 'y',
				'&omega;' => 'w'
			);
		
		foreach($greek_letters as $code => $replacement)
			$item = str_replace($code, '<span style="font-family: Symbol">'.$replacement.'</span>', $item);
			
		return $item;
	}


    /**
     * Converts the HTML contents of a Moodle page to a PDF.
     */
    public static function output_pdf($html, $return_output = true, $name='printable.pdf', $add_header=true)
    {
        //TODO: options?
        //create a new PDF writer object
        $pdf = self::create_new_pdf_writer();

        if(optional_param('pdfdebug', 0, PARAM_INT))
            $pdf->setModeDebug();

        //if add_header is set, add the PDF's header
        if($add_header)
            $html = self::generate_pdf_header() . $html;

        //pass the page's HTML to our PDF writer
        $pdf->set_html(self::pdf_preprocess($html));

        // Run the internal rendering process.
        $pdf->render();

        //retrieve the raw PDF data
        if($return_output)
            return $pdf->output('S', $name);
        else
            $pdf->output('D', $name);

    }

     /**
     * Do not call this function directly.
     *
     * To terminate the current script with a fatal error, call the {@link print_error}
     * function, or throw an exception. Doing either of those things will then call this
     * function to display the error, before terminating the execution.
     *
     * @param string $message The message to output
     * @param string $moreinfourl URL where more info can be found about the error
     * @param string $link Link for the Continue button
     * @param array $backtrace The execution backtrace
     * @param string $debuginfo Debugging information
     * @return string the HTML to output.
     */
    public function fatal_error($message, $moreinfourl, $link, $backtrace, $debuginfo = null) {
        global $CFG;

        $output = '';
        $obbuffer = '';

        // Turn off output buffering.
        ob_end_clean();

        if ($this->has_started()) {
            // we can not always recover properly here, we have problems with output buffering,
            // html tables, etc.
            $output .= $this->opencontainers->pop_all_but_last();

        } else {
            // It is really bad if library code throws exception when output buffering is on,
            // because the buffered text would be printed before our start of page.
            // NOTE: this hack might be behave unexpectedly in case output buffering is enabled in PHP.ini
            error_reporting(0); // disable notices from gzip compression, etc.
            while (ob_get_level() > 0) {
                $buff = ob_get_clean();
                if ($buff === false) {
                    break;
                }
                $obbuffer .= $buff;
            }
            error_reporting($CFG->debug);

            // Header not yet printed
            if (isset($_SERVER['SERVER_PROTOCOL'])) {
                // server protocol should be always present, because this render
                // can not be used from command line or when outputting custom XML
                @header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            }
            $this->page->set_context(null); // ugly hack - make sure page context is set to something, we do not want bogus warnings here
            $this->page->set_url('/'); // no url
            //$this->page->set_pagelayout('base'); //TODO: MDL-20676 blocks on error pages are weird, unfortunately it somehow detect the pagelayout from URL :-(
            $this->page->set_title(get_string('error'));
            $this->page->set_heading($this->page->course->fullname);
            $output .= $this->header();
        }

        $message = '<p class="errormessage">' . $message . '</p>'.
                '<p class="errorcode"><a href="' . $moreinfourl . '">' .
                get_string('moreinformation') . '</a></p>';
        if (empty($CFG->rolesactive)) {
            $message .= '<p class="errormessage">' . get_string('installproblem', 'error') . '</p>';
            //It is usually not possible to recover from errors triggered during installation, you may need to create a new database or use a different database prefix for new installation.
        }
        $output .= $this->box($message, 'errorbox');

        if (debugging('', DEBUG_DEVELOPER)) {
            if (!empty($debuginfo)) {
                $debuginfo = s($debuginfo); // removes all nasty JS
                $debuginfo = str_replace("\n", '<br />', $debuginfo); // keep newlines
                $output .= $this->notification('<strong>Debug info:</strong> '.$debuginfo, 'notifytiny');
            }
            if (!empty($backtrace)) {
                $output .= $this->notification('<strong>Stack trace:</strong> '.format_backtrace($backtrace), 'notifytiny');
            }
            if ($obbuffer !== '' ) {
                $output .= $this->notification('<strong>Output buffer:</strong> '.s($obbuffer), 'notifytiny');
            }
        }

        if (empty($CFG->rolesactive)) {
            // continue does not make much sense if moodle is not installed yet because error is most probably not recoverable
        } else if (!empty($link)) {
            $output .= $this->continue_button($link);
        }

        //$output .= $this->footer();

        // Padding to encourage IE to display our error page, rather than its own.
        $output .= str_repeat(' ', 512);

        return $output;
    }
}
