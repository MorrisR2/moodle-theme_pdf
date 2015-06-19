<?php

// The image "downloaded" from a file url to lib/tmp/ is currently zero bytes.  Other than that, it should work.
// Caching cn be restored perhaps.

    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    $CFG = new stdClass();
    ini_set('wincache.fcenabled', '0');

class webkit_pdf {
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


    public function render() {

                    $cmdstring =   '"'.$this->cmd.'" '
                            // . '"' . $this->tmp .  '" "' .$this->tmp.'.pdf"'; 
                            // .' "'.$this->tmp.'" "'.$this->tmp.'.pdf"';
                             .(($this->copies>1)?' --copies '.$this->copies:'')                      // number of copies
                             .' -O '.$this->orient
                             .' -s '.$this->size
                             .' -T 15mm -B 10mm -R 4mm -L 4mm '
                             .' --footer-center [page] '
                            .($this->header ?  ' --header-spacing 3 --header-html "'.$this->header.'"' : '')
                            .($this->toc?' --toc':'')                                              // table of contents
                            .($this->grayscale?' -g':'')                                           // grayscale
                            .(($this->title!='')?' --title "'.$this->title.'"':'')                 // title
                            .' --disable-smart-shrinking --images '
                            // . '"http://clonebox.net" "' .$this->tmp.'.pdf"'; 
                            .' "'.$this->tmp.'" "'.$this->tmp.'.pdf"';                          // Windows doesn't seem to work well with STDOUT;
                            // . '--load-error-handling ignore "https://dev-lms2.teex.tamus.edu/" "' .$this->tmp.'.pdf"';

                    $this->pdf=self::_pipeExec($cmdstring);
                    if(strpos(strtolower($this->pdf['stderr']),'error')!==false) {
                    //    echo $this->pdf['stderr'];
                    }
                    $this->pdf['stdout']= file_get_contents($this->tmp.'.pdf');
                    echo $this->pdf['stdout'];
}

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




            public function __construct(){
                global $CFG;
                //TODO: abstract this to a configuration file
                $this->cmd='C://Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe';
                // $this->cmd= $CFG->dirroot. '/theme/pdf/wkhtmltopdf.exe';
                // If we couldn't find the wkhtmltopdf binary, raise an exception.
                if(!file_exists($this->cmd)) {
                    echo "\nfile: ". __FILE__ . ' line: ' . __LINE__ . "<br />\n";
                    throw new Exception('WKPDF static executable "'.htmlspecialchars($this->cmd,ENT_QUOTES).'" was not found.');
                }
                include_once('lib/simple_html_dom/download_page.php');
                $this->tmp = download_complete_page('file:///' . realpath('lib/tmp/test3.html'), 'lib/tmp');
            }

}

header('Content-type: application/pdf');
$wk = new webkit_pdf;
$wk->render();

?>
