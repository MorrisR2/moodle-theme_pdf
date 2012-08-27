Render-to-PDF Theme for Moodle
==================================================

Authored by Kyle Temkin, working for Binghamton University <http://www.binghamton.edu>

Description
---------------

This is a special pseudo-theme which allows Moodle pages to easily be rendered to PDFs. It is intended for use by plugins, which can set the theme on a per-page basis by using $PAGE-&gt;theme. This theme contains a specialized renderer factory which allows output to be more easily formatted for printing.

For one use case, see the Paper Copy report and Printable PDF block.

Installation
-----------------

To install Moodle 2.1+ using git, execute the following commands in the root of your Moodle install:

    git clone git://github.com/ktemkin/moodle-theme_pdf.git theme/pdf
    echo '/theme/pdf' >> .git/info/exclude
    
Or, extract the following zip in your_moodle_root/theme/pdf:

    https://github.com/ktemkin/moodle-theme_pdf/zipball/master
