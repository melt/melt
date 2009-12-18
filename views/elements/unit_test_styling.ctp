</div>
<?php $this->layout->enterSection('head'); ?>
<title>nanoMVC - System Unit Testing</title>
<style type="text/css">
body {
    font: 12px Verdana;
    margin: 0;
}
.test {
    border-style: solid;
    border-width: 0 0 0 15px;
    border-color: #bfbfbf;
    background-color: #efefef;
    margin: 10px;
    padding: 5px;
    padding-left: 5px;
}
.test:first-line {
    font: bold 12px verdana;
}
.fail {
    border-style: solid;
    border-width: 0 0 0 8px;
    border-color: #f40b0b;
    background-color: #ffffff;
    margin: 5px;
    padding: 6px;
}
.pass {
    border-style: solid;
    border-width: 0 0 0 8px;
    border-color: #00cf00;
    background-color: #ffffff;
    margin: 5px;
    padding: 6px;
}
.code {
    border-style: solid;
    border-width: 1px;
    border-color: #afafaf;
    color: #5f5f5f;
    margin: 5px;
    padding: 2px;
    padding-left: 10px;
}
.hidden_arg {
    font-size: 0;
    background-color: red;
    border-style: solid;
    border-width: 2px;
    border-color: #ff6600;
}
</style>
<?php $this->layout->exitSection(); ?>