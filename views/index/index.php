<?php namespace nmvc; ?>
<?php $this->layout->enterSection("head"); ?>
    <title>Welcome to nanoMVC!</title>
    <style>
        body {
            font-size: 16px;
            font-family: sans-serif;
        }
        #wrap {
            width: 800px;
            margin: 0 auto;
        }
        li {
            margin-bottom: 16px;
        }
    </style>
<?php $this->layout->exitSection(); ?>
<div id="wrap">
    <img alt="NanoMVC Logo" src="<?php echo url("/static/logo-nanomvc.png"); ?>" />
    <h1>Welcome to NanoMVC <?php echo internal\VERSION; ?>!</h1>
    <p>
        You have successfully installed a...
    </p>
    <ul>
        <li>
            ...compact and highly advanced database abstraction layer with a
            modeling system that allows native php instance access to stored
            instances of data.
        </li>
        <li>
            ...lightweight and highly useful view renderer which use dynamic
            layouting and supports nested sections of content.
        </li>
        <li>
            ...controller architecture that allows direct access of data and
            functions in the view without explicitly passing anything.
        </li>
        <li>
            ...framework for content that uses native PHP for performance and
            lower learning curve.
        </li>
        <li>
            ...rich api that provides pragmatic generic solutions to everyday
            programming problems such as listing files in directories and
            piecing together and comparing arrays.
        </li>
        <li>
            ...session handler with uses the database for storing session data
            allowing multiple server instances to share the same session
            information.
        </li>
        <li>
            ...mailer with a built in PHP mail spooler that can handle temporary
            SMTP outages.
        </li>
        <li>
            ...mailer that handles any combination of plain text, html and
            attachments making mail while being fully RFC compatible.
        </li>
        <li>
            ...messenger which makes basic user notification as simple as it
            should be.
        </li>
        <li>
            ...interface generator called quantum model interface that enables
            programming and refactoring of user interaction to be faster and
            use less code duplication.
        </li>
        <li>
            ...class autoloader that maps classes to file locations
            unambigiously.
        </li>
        <li>
            ...framework using code styles and conventions that make sense.
        </li>
        <li>
            ...error handler and debugging api with informative error messages
            that allows you to know what's wrong at a glance.
        </li>
        <li>
            ...object oriented and non excessive class architecture
            with focus on extendability.
        </li>
        <li>
            ...framework which pragmatically utilizes namespaces which
            prevents conflicts between nanomvc and external libraries
            without making code more confusing and harder to read.
        </li>
        <li>
            ...framework which smootly integrates with vendors and 3rd party code
            and has built in PEAR autoload support.
        </li>
        <li>
            ...type handler which allows you to juggle types like you're used
            to with as little code as possible.
        </li>
        <li>
            ...database selection abstractor which escapes your SQL and builds
            queries for you.
        </li>
        <li>
            ...graph relation architecture which allows modeling and selection
            of complex enteprise level relational data structures a breeze.
        </li>
        <li>
            ...built in file cacher which allows safe uploading of files and
            images while letting the web server do the heavy static file deilvery
            work.
        </li>

        <li>
            ...thread library that allows two kinds of request forking in special
            cases where heavy work is required without interrupting the
            current request.
        </li>
        <li>
            ...parallel request/thread syncronizer which utilizes automatic InnoDB
            row locking to prevent data corruption when multiple requests access
            the same data.
        </li>
        <li>
            ...built in data structure syncronizer which allows you to develop
            and refactor your models without even writing a line of SQL to keep
            the data in sync with the database.
        </li>
        <li>
            ...localization engine which automatically parses and exports/imports
            the human text in your application to the popular PO gettext format.
        </li>
        <li>
            ...collection of other useful developer tools that allows browser
            interaction with the translation, deleting unused columns and reparing
            broken mysql relations.
        </li>
    </ul>

    <p>
        ...and much more! Now the fun begins..
        &lt;Insert your web application here.&gt; ;)
    </p>
</div>