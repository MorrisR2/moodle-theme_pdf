<?php

require_once($CFG->dirroot . '/theme/pdf/html2pdf/html2pdf.class.php');
include_once($CFG->dirroot . '/lib/htmlpurifier/HTMLPurifier.safe-includes.php');


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
    protected function create_new_pdf_writer($orientation=self::ORIENTATION_PORTRAIT, $paper_size=self::PAPER_LETTER, $language=self::LANGUAGE_ENGLISH)
    {
        //create a new HTML2PDF object
        $pdf =  new HTML2PDF($orientation, $paper_size, $language, false, 'ISO-8859-1'); //true, 'UTF-8');
        //$pdf =  new HTML2PDF($orientation, $paper_size, $language, true, 'UTF-8');

        //set the font
        $pdf->setDefaultFont('Times');

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
            if(optional_param('set_contenttype', 1, PARAM_INT))  
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
        $after = preg_replace_callback('#img(.*?) src\="'.$root_url.'/pluginfile\.php/([0-9]+)/([a-z]+)/([a-z]+)/([0-9]+)/([0-9]+)/([0-9]+)/([^"]+)#', array('core_pdf_renderer', 'rewrite_link_callback'), $html, -1);


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
        $file = $DB->get_record('files', array('component' => $matches[3], 'filearea' => $matches[4], /* 'userid' => $matches[6], */ 'itemid' => $matches[7], 'filename' => $matches[8])); 

        //if we couldn't find the given file, then return its untranslated filename
        if(!$file)
            return $matches[0];

        //calculate the main and subdir of the filename
        $main_dir = substr($file->contenthash, 0, 2);
        $sub_dir = substr($file->contenthash, 2, 2);

        //and return the file's location on disk
        return 'img '.$matches[1].' src="'.$CFG->dataroot.'/filedir/'.$main_dir.'/'.$sub_dir.'/'.$file->contenthash;
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
        $pdf->writeHTML(self::pdf_preprocess($html));

        //retrieve the raw PDF data
        if($return_output)
            return $pdf->Output('', true);
        else
            $pdf->Output($name, false);
    }
}
