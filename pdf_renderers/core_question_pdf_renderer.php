<?

// Include the core dependencies for each of the question types.
include_once($CFG->dirroot . '/question/engine/renderer.php');
include_once($CFG->dirroot . '/question/type/rendererbase.php');

/**
 * Special override for producing PDFs of question objects.
 */
class core_question_pdf_renderer extends core_question_renderer
{
    /**
     * Generate the display of a question in a particular state, and with certain
     * display options. Normally you do not call this method directly. Intsead
     * you call {@link question_usage_by_activity::render_question()} which will
     * call this method with appropriate arguments.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return string HTML representation of the question.
     */
    public function question(question_attempt $qa, qbehaviour_renderer $behaviouroutput, qtype_renderer $qtoutput, question_display_options $options, $number) 
    {

        //start a new output buffer
        $output = '';

        //add the quesiton number (TODO: style?)
        //$output .= '<strong>' . $number .'.</strong>&nbsp; &nbsp;';

        $output .= html_writer::start_tag('table', array('style' => 'width: 100%; padding-bottom: 4px;'));
        $output .= html_writer::start_tag('tr', array());
        $output .= html_writer::tag('td', $number.'.', array('valign' => 'top', 'width' => '10%', 'style' => 'padding-right: 10px;'));
        $output .= html_writer::start_tag('td', array('width' => '90%'));

        //get the question from the attempt object
        $question = $qa->get_question();
        $pragmas = self::extract_pragmas($question->format_questiontext($qa));

        //add the question's formulation
        $output .= $this->formulation($qa, $behaviouroutput, $qtoutput, $options);

        //an indication of output, if appropriate
        $output .= $this->outcome($qa, $behaviouroutput, $qtoutput, $options);

        //any manual comments, if appropriate
        $output .= $this->manual_comment($qa, $behaviouroutput, $qtoutput, $options);

        //the user's response history, if appropriate
        $output .= $this->response_history($qa, $behaviouroutput, $qtoutput, $options);

        $output .= html_writer::end_tag('td');
        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('table');

        //if a pragma exists specifying the space after a given quesiton, use it; otherwise, assume 5px
        //$space_after = array_key_exists('space_after', $pragmas) ? $pragmas['space_after'] : '5px';
        $space_after = array_key_exists('space_after', $pragmas) ? $pragmas['space_after'] : 0;

        //and add a spacer after the given question
        if($space_after !== 0)
            $output .= html_writer::tag('div', '&nbsp;', array('style' => 'height: '.$space_after.';'));

        //return the contents of the output buffer
        return $output;
    }

    
    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function formulation(question_attempt $qa, qbehaviour_renderer $behaviouroutput, qtype_renderer $qtoutput, question_display_options $options) 
    {
        //add the question's formulation (main question content)
         return $qtoutput->formulation_and_controls($qa, $options);
    }

    public static function extract_pragmas($text)
    {
        $matches = array();
        preg_match_all('<!-- pdf_pragma ([A-Za-z0-9\_]+) (.*?) -->', $text, $matches);

        //start off with an empty array of pragmas
        $pragmas = array();

        //parse the matches, and return an array of pragmas
        for($x = 0; $x < count($matches[0]); ++$x)
            $pragmas[$matches[1][$x]] = $matches[2][$x];

        //return the newly created list of PDF pragmas
        return $pragmas;
    }


}
