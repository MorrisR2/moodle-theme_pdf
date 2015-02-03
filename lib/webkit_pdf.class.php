<?php

        // Automated configuration. Modify these if they fail. (they shouldn't ;) )
        $GLOBALS['WKPDF_BASE_PATH']=str_replace(str_replace('\\','/',getcwd().'/'),'',dirname(str_replace('\\','/',__FILE__))).'/';
        // $GLOBALS['WKPDF_BASE_PATH'] = dirname(__FILE__).'/';


        /**
         * @author Christian Sciberras
         * @see <a href="http://code.google.com/p/wkhtmltopdf/">http://code.google.com/p/wkhtmltopdf/</a>
         * @copyright 2010 Christian Sciberras / Covac Software.
         * @license LGPL
         * @example
         *   <font color="#008800"><i>//-- Create sample PDF and embed in browser. --//</i></font><br>
         *   <br>
         *   <font color="#008800"><i>// Include WKPDF class.</i></font><br>
         *   <font color="#0000FF">require_once</font>(<font color="#FF0000">'wkhtmltopdf/wkhtmltopdf.php'</font>);<br>
         *   <font color="#008800"><i>// Create PDF object.</i></font><br>
         *   <font color="#EE00EE">$pdf</font>=new <b>WKPDF</b>();<br>
         *   <font color="#008800"><i>// Set PDF's HTML</i></font><br>
         *   <font color="#EE00EE">$pdf</font>-><font color="#0000FF">set_html</font>(<font color="#FF0000">'Hello &lt;b&gt;Mars&lt;/b&gt;!'</font>);<br>
         *   <font color="#008800"><i>// Convert HTML to PDF</i></font><br>
         *   <font color="#EE00EE">$pdf</font>-><font color="#0000FF">render</font>();<br>
         *   <font color="#008800"><i>// Output PDF. The file name is suggested to the browser.</i></font><br>
         *   <font color="#EE00EE">$pdf</font>-><font color="#0000FF">output</font>(<b>WKPDF</b>::<font color="#EE00EE">$PDF_EMBEDDED</font>,<font color="#FF0000">'sample.pdf'</font>);<br>
         * @version
         *   0.0 Chris - Created class.<br>
         *   0.1 Chris - Variable paths fixes.<br>
         *   0.2 Chris - Better error handlng (via exceptions).<br>
         * <font color="#FF0000"><b>IMPORTANT: Make sure that there is a folder in %LIBRARY_PATH%/tmp that is writable!</b></font>
         * <br><br>
         * <b>Features/Bugs/Contact</b><br>
         * Found a bug? Want a modification? Contact me at <a href="mailto:uuf6429@gmail.com">uuf6429@gmail.com</a> or <a href="mailto:contact@covac-software.com">contact@covac-software.com</a>...
         *   guaranteed to get a reply within 2 hours at most (daytime GMT+1).
         */
        class webkit_pdf {
            /**
             * Private use variables.
             */
            private $html='';
            private $cmd='';
            private $tmp='';
            private $pdf='';
            private $status='';
            private $orient='Portrait';
            private $size='Letter';
            private $toc=false;
            private $copies=1;
            private $grayscale=false;
            private $title='';
            private $footer='';
            private $header='';
            /**
             * Advanced execution routine.
             * @param string $cmd The command to execute.
             * @param string $input Any input not in arguments.
             * @return array An array of execution data; stdout, stderr and return "error" code.
             */
            private static function _pipeExec($cmd,$input=''){
                    // array('bypass_shell' => 1)
                    $proc=proc_open("\"$cmd\"",array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes, null, array('bypass_shell' => 1));
                    fwrite($pipes[0],$input);
                    fclose($pipes[0]);
                    $stdout=stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    $stderr=stream_get_contents($pipes[2]);
                    fclose($pipes[2]);
                    $rtn=proc_close($proc);
                    return array(
                                    'stdout'=>$stdout,
                                    'stderr'=>$stderr,
                                    'return'=>$rtn
                            );
            }


            /**
             * Creates a unique, temporary file with the given extension; and returns its name.
             * 
             * @param mixed $location   The location in which the file will be created; without the trailing slash.
             * @param mixed $extension  The extension that the file should have, not including a period.
             * @param mixed $prefix     A short, optional prefix for the file.
             * @return string           The name of the created file.
             */
            private static function tempnam_with_extension($location, $extension, $prefix = '') {

                // Create a file with a highly-unique name. 
                // If that file exists, try again until we find a filename that does not.
                do {
                    $filename = $location.'/'.$prefix;
                    $filename .= ip2long($_SERVER['REMOTE_ADDR']);
                    $filename .= '_'. uniqid();
                    $filename .= '.'.$extension;
                } while(file_exists($filename));

                // Create the file, so no other thread will accidentally create the same file. 
                // Note that this _not_ an atomic operation- but PHP doesn't offer any better kind of locking.
                touch($filename);

                // Return the newly created filename. 
                return $filename;
            }

           /**
             * Force the client to download PDF file when finish() is called.
             */
            const PDF_DOWNLOAD='D';
            /**
             * Returns the PDF file as a string when finish() is called.
             */
            const PDF_AS_STRING='S';
            /**
             * When possible, force the client to embed PDF file when finish() is called.
             */
            const PDF_EMBEDDED='I';
            /**
             * PDF file is saved into the server space when finish() is called. The path is returned.
             */
            const PDF_SAVEFILE='F';
            /**
             * PDF generated as landscape (vertical).
             */
            const PDF_PORTRAIT='Portrait';
            /**
             * PDF generated as landscape (horizontal).
             */
            const PDF_LANDSCAPE='Landscape';

            /**
             * Constructor: initialize command line and reserve temporary file.
             */
            public function __construct(){

                //TODO: abstract this to a configuration file
                $this->cmd='C://Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe';
                // If we couldn't find the wkhtmltopdf binary, raise an exception.
                if(!file_exists($this->cmd)) {
                    throw new Exception('WKPDF static executable "'.htmlspecialchars($this->cmd,ENT_QUOTES).'" was not found.');
                }

                // Create a temporary file to store the raw HTML.
                // TODO: replace with Moodle's temp path.
                $this->tmp = self::tempnam_with_extension($GLOBALS['WKPDF_BASE_PATH'] . 'tmp', 'html', 'pdf');
            }
            /**
             * Set orientation, use constants from this class.
             * By default orientation is portrait.
             * @param string $mode Use constants from this class.
             */
            public function set_orientation($mode){
                    $this->orient=$mode;
            }
            /**
             * Set page/paper size.
             * By default page size is A4.
             * @param string $size Formal paper size (eg; A4, letter...)
             */
            public function set_page_size($size){
                    $this->size=$size;
            }
            /**
             * Whether to automatically generate a TOC (table of contents) or not.
             * By default TOC is disabled.
             * @param boolean $enabled True use TOC, false disable TOC.
             */
            public function set_toc($enabled){
                    $this->toc=$enabled;
            }
            /**
             * Set the number of copies to be printed.
             * By default it is one.
             * @param integer $count Number of page copies.
             */
            public function set_copies($count){
                    $this->copies=$count;
            }
            /**
             * Whether to print in grayscale or not.
             * By default it is OFF.
             * @param boolean True to print in grayscale, false in full color.
             */
            public function set_grayscale($mode){
                    $this->grayscale=$mode;
            }
            /**
             * Set PDF title. If empty, HTML <title> of first document is used.
             * By default it is empty.
             * @param string Title text.
             */
            public function set_title($text){
                    $this->title=$text;
            }
            /**
             * Set html content.
             * @param string $html New html content. It *replaces* any previous content.
             */
            public function set_html($html){
                    $this->html=$html;
                    file_put_contents($this->tmp,$html);
            }

            public function set_header($html) {
                //TODO: abstract to config
                $this->header = self::tempnam_with_extension($GLOBALS['WKPDF_BASE_PATH'] . 'tmp', 'html', 'header');
                file_put_contents($this->header, $html);
            }

            public function set_footer($html) {
                //TODO: abstract to config
                $this->footer = self::tempnam_with_extension($GLOBALS['WKPDF_BASE_PATH'] . 'tmp', 'html', 'footer');
                file_put_contents($this->footer, $html);
            }

            /**
             * Returns WKPDF print status.
             * @return string WPDF print status.
             */
            public function get_status(){
                    return $this->status;
            }

            /**
             * Convert HTML to PDF.
             */
            public function render(){

/*
echo
                            '\'"'.$this->cmd.'"'
                            .(($this->copies>1)?' --copies '.$this->copies:'')                              // number of copies
                            .' -O '.$this->orient                                                                // orientation
                            .' -s '.$this->size                                                                    // page size
                            .' -T 15mm -B 10mm -R 4mm -L 4mm '
                            .' --footer-center [page] '
                            .($this->header ?  ' --header-spacing 3 --header-html '.$this->header : '')
                            .($this->toc?' --toc':'')                                                                               // table of contents
                            .($this->grayscale?' -g':'')                                                   // grayscale
                            .(($this->title!='')?' --title "'.$this->title.'"':'')                  // title
                            .' "'.$this->tmp.'" -\''                                                  // URL and optional to write to STDOUT
;
exit;
*/
                    $oldcwd = getcwd();
                    chdir($GLOBALS['WKPDF_BASE_PATH']);

/*
                    $this->pdf=self::_pipeExec(
                            '"'.$this->cmd.'"'
                            .(($this->copies>1)?' --copies '.$this->copies:'')                              // number of copies
                            .' -O '.$this->orient                                                                // orientation
                            .' -s '.$this->size                                                                    // page size
                            .' -T 15mm -B 10mm -R 4mm -L 4mm '
                            .' --footer-center [page] '
                            .($this->header ?  ' --header-spacing 3 --header-html '.$this->header : '')
                            .($this->toc?' --toc':'')                                                                               // table of contents
                            .($this->grayscale?' -g':'')                                                   // grayscale
                            .(($this->title!='')?' --title "'.$this->title.'"':'')                  // title
                            .' "'.$this->tmp.'" -'                                                  // URL and optional to write to STDOUT
                        );
*/


                    $cmdstring =   '"'.$this->cmd.'"'
                            .' "'.$this->tmp.'" "'.$this->tmp.'.pdf"';                                      // URL and optional to write to STDOUT;
 

                    $cmdstring =
                            '"'.$this->cmd.'"'
                            .(($this->copies>1)?' --copies '.$this->copies:'')                              // number of copies
                            .' -O '.$this->orient                                                                // orientation
                            .' -s '.$this->size                                                                    // page size
                            .' -T 15mm -B 10mm -R 4mm -L 4mm '
                            .' --footer-center [page] '
                            .($this->header ?  ' --header-spacing 3 --header-html "'.$this->header . '"' : '')
                            .($this->toc?' --toc':'')                                                                               // table of contents
                            .($this->grayscale?' -g':'')                                                   // grayscale
                            .(($this->title!='')?' --title "'.$this->title.'"':'')                  // title
                            .' "'.$this->tmp.'" "'.$this->tmp.'.pdf"'                               // temporary output file because stdout doesn't work on Windows
                        ;


                    $cmdstring =
                            '"'.$this->cmd.'"'
                            .' --load-media-error-handling ignore'
                            .' --load-error-handling ignore'
                            .(($this->copies>1)?' --copies '.$this->copies:'')                              // number of copies
                            .' -O '.$this->orient                                                                // orientation
                            .' -s '.$this->size                                                                    // page size
                            .' -T 15mm -B 10mm -R 4mm -L 4mm '
                            .' --footer-center [page] '
                            .($this->toc?' --toc':'')                                                                               // table of contents
                            .($this->grayscale?' -g':'')                                                   // grayscale
                            .(($this->title!='')?' --title "'.$this->title.'"':'')                  // title
                            .' "'.$this->tmp.'" "'.$this->tmp.'.pdf"'                               // temporary output file because stdout doesn't work on Windows
                        ;

                    $cmdstring =
                            '"'.$this->cmd.'"'
                            .' --load-media-error-handling ignore'
                            .' --load-error-handling ignore'
                            .(($this->copies>1)?' --copies '.$this->copies:'')                              // number of copies
                            .' -O '.$this->orient                                                                // orientation
                            .' -s '.$this->size                                                                    // page size
                            .' -T 15mm -B 10mm -R 4mm -L 4mm '
                            .' --footer-center [page] '
                            .($this->toc?' --toc':'')                                                                               // table of contents
                            .($this->grayscale?' -g':'')                                                   // grayscale
                            .(($this->title!='')?' --title "'.$this->title.'"':'')                  // title
                            .' "E:/Data/Web/moodle/theme/pdf/lib/tmp/pdf-1520702075_54b96817c898c_nogoog.html" "'.$this->tmp.'.pdf"'                               // temporary output file because stdout doesn't work on Windows
                        ;


                    $this->pdf=self::_pipeExec($cmdstring);
                    $this->pdf['stdout']= file_get_contents($this->tmp.'.pdf');

                    if(strpos(strtolower($this->pdf['stderr']),'error')!==false) {
                        throw new Exception('WKPDF system error: <pre>'.$this->pdf['stderr']. $cmdstring . '</pre>'); 
                    }

                    @unlink($this->tmp);
                    if (file_exists($this->tmp.'.pdf')) {
                        @unlink($this->tmp.'.pdf');
                    }

                    if($this->pdf['stdout']=='') {
                        chdir($oldcwd);
                        throw new Exception('WKPDF didn\'t return any data. <pre>'.$this->pdf['stderr'].'</pre>');
                    }

                    if ((int)$this->pdf['return']  > 2) {
                        chdir($oldcwd);
                        throw new Exception('WKPDF shell error, return code '.(int)$this->pdf['return'].'.');
                    }

                    $this->status=$this->pdf['stderr'];
                    $this->pdf=$this->pdf['stdout'];


                    if($this->header) {
                        unlink($this->header);
                    }

                    if($this->footer) {
                        unlink($this->footer);
                    }
                    chdir($oldcwd);

                // echo $this->pdf; exit;
            }
            /**
             * Return PDF with various options.
             * @param string $mode How two output (constants from this same class).
             * @param string $file The PDF's filename (the usage depends on $mode.
             * @return string|boolean Depending on $mode, this may be success (boolean) or PDF (string).
             */
            public function output($mode,$file=''){
                switch($mode) {

                    case self::PDF_DOWNLOAD:

                        if (!headers_sent()) {
                            echo $this->pdf;
                        } else {
                            throw new Exception('WKPDF download headers were already sent.');
                        }
                        
                        break;

                    case self::PDF_AS_STRING:
                        return $this->pdf;

                    case self::PDF_EMBEDDED:
                        if(!headers_sent()){
                            echo $this->pdf;
                        }else{
                            throw new Exception('WKPDF embed headers were already sent.');
                        }
                        break;

                    case self::PDF_SAVEFILE:
                        return file_put_contents($file,$this->pdf);

                    default:
                        throw new Exception('WKPDF invalid mode "'.htmlspecialchars($mode,ENT_QUOTES).'".');
                }
                return false;
            }

            /**
             * Sends the proper HTTP headers to initialize a forced download.
             * (This prevents browsers from embedding the PDF in the page.)
             */
            public static function send_download_headers($file_length = null, $file_name = 'printable.pdf') {
                header('Content-Description: File Transfer');
                header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
                header('Pragma: public');
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');

                // force download dialog
                header('Content-Type: application/force-download');
                header('Content-Type: application/octet-stream', false);
                header('Content-Type: application/download', false);
                header('Content-Type: application/pdf', false);

                // use the Content-Disposition header to supply a recommended filename
                header('Content-Disposition: attachment; filename="'.$file_name.'";');
                header('Content-Transfer-Encoding: binary');

                if($file_length !== null) {
                    header('Content-Length: '.$file_length);
                }
            }

            /**
             * Sends the proper HTTP headers to inform the browser that this is a PDF.
             * In most cases, the file will be embedded.
             */
            public static function send_embed_headers($file_length = null, $file_name ='printable.pdf') {
                header('Content-Type: application/pdf');
                header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
                header('Pragma: public');
                header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
                header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');

                if($file_length !== null) {
                    header('Content-Length: '.$file_length);
                }

                header('Content-Disposition: inline; filename="'.$file_name.'";');
            }
    }
?>
