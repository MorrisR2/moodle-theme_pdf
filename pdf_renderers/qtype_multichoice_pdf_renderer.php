<?php

require_once($CFG->dirroot.'/theme/pdf/pdf_renderers/core_question_pdf_renderer.php');
require_once($CFG->dirroot.'/question/type/multichoice/renderer.php');

abstract class qtype_multichoice_pdf_renderer_base extends qtype_multichoice_renderer_base
{

    //TODO: abstract these to library, especially print_answers_as_table, and related
    const QUESTION_MAXIMUM_WIDTH = 88;
    const APPROX_LINE_WIDTH = 125;

    /**
     * Print out a set of answers as a table.
     */
    static function print_answers_as_table($question_text, $possible_answers, $cols = 1, $pragmas = array())
    {
        //initalize a new output buffer
        $output = '';

        //and start a new column count
        $col = -1;

        //make sure we can never have more columns than answers
        $cols = min($cols, count($possible_answers));

        //if a pdf_pragma exists overriding the number of columns, accept it
        if(array_key_exists('cols', $pragmas))
            $cols = $pragmas['cols'];

        //if a style pragma was provided, use it
        if(array_key_exists('answerstyle', $pragmas))
            $style_pragma = $pragmas['answerstyle'];
        else
            $style_pragma = '';

        //calculate the correct column width for each item, reserving 2% for the identifiers
        $col_width = floor(self::QUESTION_MAXIMUM_WIDTH / $cols) - 2;

        //start the table
        $output .= "\n".html_writer::start_tag('table', array('class' => 'fullwidth', 'style' => 'width: 95%;'));

        //output the question
        $output .= "\n".html_writer::start_tag('tr');
        $output .= "\n".html_writer::tag('td', $question_text, array('colspan' => $cols * 2, 'width' => '95%'));
        $output .= "\n".html_writer::end_tag('tr');


        //for each of the possible answers
        foreach($possible_answers as $possible_answer)
        {
            //calculate the current column number, and set newcol if we should start a new column
            $col = ($col + 1) % $cols;

            //if this is the first element in a column, 
            if($col === 0)
                $output .= "\n".html_writer::start_tag('tr');

            //TODO: weight column width by content?
            //(leads to better layout, but columns will no longer line up)

            //render the possible answer
            $output .= "\n".html_writer::tag('td', $possible_answer['identifier'], array('class' => 'identifier', 'width' => '2%' ));
            $output .= "\n".html_writer::tag('td', $possible_answer['text'], array('style' => 'width: '.$col_width.'%; padding-left: 10px; vertical-align: middle;'.$style_pragma)); 

            //if we've reached the maximum amount of columns, end the row
            if($col == $cols - 1)
                $output .= "\n".html_writer::end_tag('tr');
        }

        //if our row is unfinished, finish it 
        if($col < $cols - 1)
        {
            //insert a column as wide as there are rows remaining
            $output .= "\n".html_writer::tag('td', '&nbsp;', array('colspan' => $cols - $col - 1));

            //and close the row
            $output .= "\n".html_writer::end_tag('tr');
        }

        //end the table
        $output .= "\n".html_writer::end_tag('table'); 

        return $output;
    }



    /**
     * Render the question's text.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) 
    {
        //get the question, and any response
        $question = $qa->get_question();
        $response = $question->get_response($qa);

        //start a new output buffer 
        $output = '';

        //get the question text
        $question_text = $question->make_html_inline($question->format_questiontext($qa));

        //extract any pragmas from the question text
        $question_pragmas = core_question_pdf_renderer::extract_pragmas($question_text);

        //begin tracking possible answers
        $possible_answers = array();

        //for each of the possible answers (in the possibly randomized order specified by the question)
        foreach($question->get_order($qa) as $value => $ansid)
        {
            //get the answer object for the given possible answer
            $answer = $question->answers[$ansid]; 

            //get the answer's text
            $text = $question->make_html_inline($question->format_text($answer->answer, $answer->answerformat, $qa, 'question', 'answer', $ansid));

            //preprocess (removes leading trailing space)
            self::preprocess_answer($text);

            $possible_answers[] = 
                array
                (
                    //set the identifier, which indicates the choice number
                    'identifier' => $this->number_in_style($value, $question->answernumbering),

                    //set the answer text
                    'text' => $text
                );
        }

        //automatically detect columns from the answers values
        $cols = self::detect_cols_from_answers($possible_answers);

        //generate the choice output
        $output .= self::print_answers_as_table($question_text, $possible_answers, $cols, $question_pragmas);
        
        return $output;
    }

    static function preprocess_answer(&$answer)
    {
        //trim leading or trailing whitespace
        $answer = trim($answer);

        //trim leading or trailing _unicode_ whitespace
        $answer = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $answer);

        //trim leading or trailing whitespace markers <br> / </p>   
        $whitespace = '(</?(br|p) ?/?[^>]*>)';
        $answer = preg_replace('~(^'.$whitespace.'|'.$whitespace.'$)~i', '', $answer);
    }

    protected function number_html($qnum) 
    {
        return '('.$qnum . ')';
    }


    
    /**
     * TODO: abstract to library?
     */
    public static function detect_cols_from_answers($possible_answers)
    {
        return 1;
        //get the length of the text for the longest HTML line
        $len = self::longest_line_length($possible_answers);

        //use the approximate line width to calculate the amount of columns
        return max(floor(self::APPROX_LINE_WIDTH / max($len, 1)), 1);
    
    }

    /**
     * TODO: abstract to library
     */
    public static function longest_line_length($possible_answers)
    {
        $max = 1;

        foreach($possible_answers as $answer)
        {
            //break the answer text into lines
            $lines = self::break_into_lines($answer['text']);

            //strip all HTML tags from the lines
            $lines = array_map('strip_tags', $lines);

            //get the local maximum line length
            $local_max = max(array_map('strlen', $lines));

            
            //and use the maximum of the local and global line lengths
            $max = max($max, $local_max); 
        }

        return $max;

    }

    public static function break_into_lines($line, $existing = array())
    {
        if(is_array($line))
            $line = $line[0];

        //handle the most common HTML elements- paragraphs, tables
        $to_handle = 
            array
            (
                '%<p[^>]*>(.*)</p>%i', //paragraphs
                '%<td[^>]*>(.*)</td>%i', //table columns
            );

        //recursive case- one our of target regular expressions matches
        foreach($to_handle as $preg)
            if(preg_match_all($preg, $line, $matches, PREG_PATTERN_ORDER))
            {
                //apply break_into_lines to each match
                return array_merge($existing, array_map(array(__CLASS__, 'break_into_lines'), $matches));
            }

        //base case- neither of our expressions matched, so split our line by its linebreaks and return
        $array =  preg_split('%<br[^>]*>%i', $line);
        return array_merge($existing, $array);
    }

}

class qtype_multichoice_single_pdf_renderer extends qtype_multichoice_pdf_renderer_base
{
    protected function get_input_type() {
        return 'radio';
    }

    protected function get_input_name(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('answer');
    }

    protected function get_input_value($value) {
        return $value;
    }

    protected function get_input_id(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('answer' . $value);
    }

    protected function is_right(question_answer $ans) {
        return $ans->fraction;
    }

    protected function prompt() 
    {
        return '';
    }


    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        foreach ($question->answers as $ansid => $ans) 
        {
            if (question_state::graded_state_for_fraction($ans->fraction) ==  question_state::$gradedright) {
                return get_string('correctansweris', 'qtype_multichoice',
                        $question->format_text($ans->answer, $ans->answerformat,
                                $qa, 'question', 'answer', $ansid));
            }
        }

        return '';
    }

}


class qtype_multichoice_multi_pdf_renderer extends qtype_multichoice_multi_renderer
{

}

