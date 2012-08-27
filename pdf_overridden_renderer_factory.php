<?php

class pdf_overridden_renderer_factory extends theme_overridden_renderer_factory
{
    /**
     * Implement the subclass method
     * @param moodle_page $page the page the renderer is outputting content for.
     * @param string $component name such as 'core', 'mod_forum' or 'qtype_multichoice'.
     * @param string $subtype optional subtype such as 'news' resulting to 'mod_forum_news'
     * @param string $target one of rendering target constants
     * @return object an object implementing the requested renderer interface.
     */
    public function get_renderer(moodle_page $page, $component, $subtype = null, $target = null) 
    {

        //calculate the PDF renderer name for the given class type
        $classname = $component.($subtype !== null ? '_'.$subtype : '').'_pdf_renderer';
        
        //and, if that class exists, use it
        if(class_exists($classname))
            return new $classname($page, $target);

        //otherwise, if we didn't find an alternate renderer, delegate to the base class
        return parent::get_renderer($page, $component, $subtype, $target);
    } 
}
