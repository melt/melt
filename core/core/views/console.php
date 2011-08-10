<?php namespace melt\core; ?>
<script type="text/javascript">
    $(function() {
        var cmd_line_escape_fn = function(token, no_quote_escape) {
            if (token == "")
                return "\"\"";
            if (token.match(/[ ]/) && !no_quote_escape) {
                // Quote-escape it.
                return "\"" + token.replace(/\\([ "\\])/g, "\\$1").replace(/"/g, "\\\"") + "\"";
            } else {
                // Slash-escape it.
                return token.replace(/\\([ "\\])/g, "\\$1").replace(/\\"/g, "\\\\\"").replace(/"/g, "\\\"").replace(/[ ]/g, "\\ ");
            }
        };
        var get_cmd_line_tokens_fn = function(cmd_line) {
            var tokens = [];
            var token = "";
            var in_string = false;
            var in_escape = false;
            var trailing_space = false;
            var create_token_fn = function(allow_empty) {
                if (!allow_empty && token == "")
                    return;
                tokens.push(token);
                token = "";
            };
            for (var i = 0; i < cmd_line.length; i++) {
                trailing_space = false;
                var c = cmd_line.charAt(i);
                switch (c) {
                case " ":
                    if (!in_escape && !in_string) {
                        trailing_space = true;
                        create_token_fn();
                        continue;
                    }
                    token += " ";
                    in_escape = false;
                    break;
                case "\\":
                    if (!in_escape) {
                        in_escape = true;
                        continue;
                    }
                    token += "\\";
                    in_escape = false;
                    break;
                case "\"":
                    if (!in_escape) {
                        create_token_fn(in_string);
                        in_string = !in_string;
                        continue;
                    }
                    token += "\"";
                    in_escape = false;
                    break;
                default:
                    if (in_escape) {
                        token += "\\";
                        in_escape = false;
                    }
                    token += c;
                    break;
                }
            };
            create_token_fn();
            if (tokens.length == 0)
                tokens.push("");
            return [tokens, trailing_space];
        };
	var read_file_text_fn = function(file, callback) {
            if ("FileReader" in window) {
                var reader = new FileReader();
                reader.readAsText(file, "UTF-8");
                reader.onload = function() {
                    callback(reader.result);
                };
            } else {
                return callback(file.getAsText("UTF-8"));
            }
	};
        var get_file_upload_body_fn = function(event, expected_length, component_name, complete_fn) {
            var data = event.dataTransfer;
            if (!data || data.files.length != expected_length) {
                complete_fn(null, null);
                return;
            }
            // Reading data.files ASAP since chrome resets the FileList
            // far to early for us to do something sensible with it.
            var files = [];
            for (var i = 0; i < data.files.length; i++)
                files.push(data.files[i]);
            var loaded_files = 0;
            var text_file_data = new Array(files.length);
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                read_file_text_fn(file, function(position) { return function(text_data) {
                    text_file_data[position] = text_data;
                    loaded_files++;
                    if (loaded_files === files.length) {
                        var boundary = '------multipartformboundary' + (new Date).getTime();
                        var dashdash = '--';
                        var crlf     = '\r\n';
                        /* Build RFC2388 string. */
                        var body = '';
                        body += dashdash;
                        body += boundary;
                        body += crlf;
                        /* For each dropped file. */
                        for (var i = 0; i < files.length; i++) {
                            var file = files[i];
                            /* Generate headers. */
                            body += 'Content-Disposition: form-data; name="' + component_name + '"';
                            if (file.fileName)
                                body += '; filename="' + file.fileName + '"';
                            body += crlf;
                            body += 'Content-Type: application/octet-stream';
                            body += crlf;
                            body += crlf;
                            /* Append text data. */
                            body += text_file_data[i];
                            body += crlf;
                            /* Write boundary. */
                            body += dashdash;
                            body += boundary;
                            body += crlf;
                        }
                        /* Mark end of the request. */
                        body += dashdash;
                        body += boundary;
                        body += dashdash;
                        body += crlf;
                        complete_fn(body, boundary);
                    }
                }}(i));
            }
        };
        var console_base = <?php echo \json_encode(url(REQ_URL)); ?>;
        var sync_get_fn = function(action, data) {
            var ret = null;
            $.ajax({
                async: false,
                data: data,
                url: console_base + "/" + action,
                type: "get",
                success: function(data) {
                    ret = data;
                }
            });
            return ret;
        };
        var config_names = null;
        var get_configs_names_fn = function() {
            if (!config_names)
                config_names = sync_get_fn("config_get_all");
            return config_names;
        };
        var get_tokens_fn = function(path, get_params) {
            var objects = sync_get_fn(path, get_params);
            var ret = {};
            $.each(objects.split(/\s+/), function(i, obj) {
                if (obj == "")
                    return;
                ret[obj] = true;
            });
            return ret;
        };
        var get_objects_fn = function(object, app) {
            return get_tokens_fn("cmd_obj/" + object, app? {"app": "true"}: {});
        };
        var cmd_line_init = "melt>";
        var obj_app_tree_fn = function(app, cat) {
            var get_objects_fn_fn = function(object) {
                return function() { var r = get_objects_fn(object, app); if (!cat && object !== "actions") r["make"] = true; return r; };
            };
            var ret = {
                "classes": get_objects_fn_fn("classes"),
                "controllers": get_objects_fn_fn("controllers"),
                "actions": get_objects_fn_fn("actions"),
                "models": get_objects_fn_fn("models"),
                "views": get_objects_fn_fn("views"),
                "types": get_objects_fn_fn("types")
            };
            if (!cat)
                ret["cat"] = obj_app_tree_fn(app, true);
            return ret;
        };
        var tab_tree = {
            "db": {
                "sync": true,
                "purify": true,
                "cull": true,
                "repair": true
            },
            "locale": {
                "create": true,
                "remove": true,
                "export": true,
                "import": true
            },
            "rewrite": true,
            "clear": true,
            "session": { "restart": true },
            "info": true,
            "install": true,
            "reload": true,
            "logout": true,
            "config": get_configs_names_fn,
            "app": obj_app_tree_fn(true),
            "sys": obj_app_tree_fn(false),
            "versions": true,
            "ghd": {
                "deploy-core": true,
                "deploy-module": {
                    "melt/module-data-tables": true
                },
                "deploy-sample-app": {
                    "melt/sample-app-default": true,
                    "melt/sample-app-facebook": true
                }
            }
        };
        var color_command = "#8e8";
        var color_output = "#ddd"
        var color_tabhint = "#99e"
        var carret_div = $("<div>").css({
            "display": "inline-block",
            "width": "8px",
            "height": "10px",
            "margin-left": "2px",
            "border-bottom": "solid 3px #ccc",
            "vertical-align": "top"
        });
        var output_div = $("<div>").css({
            "word-wrap": "break-word",
            "white-space": "pre-wrap"
        });
        var command_text = $("<span>");
        var input_div = $("<div>").css({
            "color": color_command,
            "display": "none",
            "overflow": "hidden",
            "word-wrap": "break-word",
            "white-space": "pre-wrap"
        }).text(cmd_line_init).append(command_text).append(carret_div);
        var input = $("<input>").css({
            "position": "fixed",
            "top": "15000px"
        });
        var input_pipe = false;
        var cmd_line = "";
        var cmd_history = [];
        var cmd_history_at = null;
        var cmd_history_current = "";
        var print_fn = function(output, color) {
            var text = document.createTextNode(output);
            if (color != null)
                text = $("<span>").css("color", color).append(text);
            output_div.append(text);
            $(window).scrollTop($("body").height());
        };
        var update_cmd_line_fn = function(new_cmd_line) {
            command_text.text(new_cmd_line);
            cmd_line = new_cmd_line;
            input_div.show();
            $(window).scrollTop($("body").height());
        };
        var carret_effect_fn = function() {
            window.setTimeout(function() {
                carret_div.hide();
                window.setTimeout(function() {
                    carret_div.show();
                    carret_effect_fn();
                }, 500);
            }, 500);
        };
        carret_effect_fn();
        var input_fn = function(done_fn, supress) {
            var old_input_pipe = input_pipe;
            var in_container = $("<span>");
            var in_span = $("<span>");
            if (supress)
                in_span.css("visibility", "hidden");
            in_container.append(in_span).append(carret_div);
            output_div.append(in_container);
            input_pipe = function(keycode, str) {
                $(window).scrollTop($("body").height());
                if (keycode < 0x20) {
                    if (keycode == 0x0d) {
                        input_pipe = old_input_pipe;
                        var input = in_span.text();
                        input_div.append(carret_div);
                        in_span.detach();
                        print_fn((supress? "": input) + "\n");
                        done_fn(input);
                    } else if (keycode == 0x08) {
                        var input = in_span.text();
                        in_span.text(input.substr(0, input.length - 1));
                    }
                } else {
                    var input = in_span.text();
                    in_span.text(input + str);
                }
            };
        };
        var mass_input_fn = function(queries, done_fn) {
            var answers = [];
            var reinput_fn = function() {
                var query = queries.shift();
                if (!query) {
                    done_fn(answers);
                    return;
                }
                var label = query[0];
                var def = query[1];
                print_fn(label + " [" + def + "]:");
                input_fn(function(input) {
                    if (input == "")
                        input = def;
                    answers.push(input);
                    reinput_fn();
                });
            };
            reinput_fn();
        };
        var login_fn = function() {
            var begin_fn = function() {
                input_pipe = false;
                update_cmd_line_fn("");
            };
            var is_logged_in_fn = function() {
                return sync_get_fn("check_login");
            };
            if (is_logged_in_fn() != null) {
                begin_fn();
                return;
            }
            var query_password_fn = function() {
                print_fn("Enter developer key: ");
                input_fn(function(password) {
                    $.cookie('MELT_DEVKEY', password, {expires: 365, path: '/'});
                    var motd = is_logged_in_fn();
                    if (motd != null) {
                        print_fn("\n" + motd + "\n");
                        begin_fn();
                        return;
                    }
                    print_fn("Password was rejected, please try again.\n\n");
                    query_password_fn();
                }, true);
            };
            query_password_fn();
        };
        var yes_eval_fn = function(input, default_yes) {
            input = $.trim(input);
            if (input == "") {
                return default_yes == true;
            } else {
                return input.substr(0, 1) == "y"
                || input.substr(0, 1) == "Y";
            }
        };
        var exec_ajax_fn = function(url, done_fn, data) {
            var response_offset = 0;
            var xhr = new XMLHttpRequest();
            if (data)
                url += "?" + $.param(data);
            xhr.open("GET", url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 3 || xhr.readyState == 4) {
                    if (xhr.status > 0 && xhr.status != 200) {
                        if (xhr.status == 403) {
                            print_fn("You are no longer logged in.\n");
                            xhr.onreadystatechange = null;
                            login_fn();
                            return;
                        } else {
                            print_fn("Error: " + xhr.status + "\n");
                            xhr.onreadystatechange = null;
                            return done_fn();
                        }
                    } else if (xhr.responseText != null) {
                        $("#direct-task-result").append();
                        if (response_offset < xhr.responseText.length) {
                            var chunk = xhr.responseText.substring(response_offset);
                            print_fn(chunk);
                        }
                        response_offset = xhr.responseText.length;
                    }
                    if (xhr.readyState >= 4) {
                        xhr.onreadystatechange = null;
                        done_fn();
                    }
                }
            };
            xhr.send();
        };
        var exec_fn = function(current_cmd, exec_done_fn) {
            var complete_fn = function() {
                if (exec_done_fn !== undefined)
                    exec_done_fn();
            };
            if (input_pipe === false) {
                complete_fn = function() {
                    input_pipe = false;
                    input_div.show();
                    update_cmd_line_fn("");
                    if (exec_done_fn !== undefined)
                        exec_done_fn();
                };
                input_pipe = function() {};
            };
            print_fn(cmd_line_init + current_cmd + "\n", color_command);
            input_div.hide();
            if (cmd_history[cmd_history.length - 1] !== current_cmd) {
                cmd_history.push(current_cmd);
                if (cmd_history.length > 32)
                    cmd_history = cmd_history.slice(1);
                cmd_history_at = null;
                cmd_history_current = "";
            }
            var cmd_tokens = get_cmd_line_tokens_fn(current_cmd)[0];
            switch (cmd_tokens[0]) {
            case "":
                complete_fn();
                break;
            case "cd":
            case "cwd":
            case "mv":
            case "ls":
            case "ll":
                print_fn("The console is not connected to an actual file system. (yet?)\n");
                complete_fn();
                break;
            case "clear":
                output_div.empty();
                complete_fn();
                break;
            case "app":
            case "sys":
                var get_data = {};
                if (cmd_tokens[1] === "cat") {
                    cmd_tokens.splice(1, 1);
                    get_data["cat"] = "true";
                }
                if (cmd_tokens[0] === "app")
                    get_data["app"] = "true";
                if (cmd_tokens[2] !== undefined) {
                    if (cmd_tokens[2] === "make") {
                        if (cmd_tokens[3] === undefined) {
                            print_fn("Error: No name supplied.\n");
                            complete_fn();
                            return;
                        }
                        get_data["make"] = cmd_tokens[3];
                    } else {
                        get_data["obj"] = cmd_tokens[2];
                    }
                }
                exec_ajax_fn(console_base + "/cmd_obj/" + cmd_tokens[1], complete_fn, get_data);
                break;
            case "rewrite":
                var action_path = $.trim(cmd_tokens[1]);
                if (action_path == "" || action_path.charAt(0) != "/") {
                    print_fn("Error: Rewrite takes an action path and must start with \"/\".\n");
                    complete_fn();
                } else {
                    exec_ajax_fn(console_base + "/cmd_rewrite" + action_path, complete_fn);
                }
                break;
            case "ghd":
                switch (cmd_tokens[1]) {
                case "deploy-core":
                case "deploy-module":
                case "deploy-sample-app":
                    var path, warning_msg;
                    if (cmd_tokens[1] === "deploy-sample-app") {
                        path = "cmd_ghd_deploy_sample_app";
                        warning_msg = "This will overwrite any existing application data.";
                    } else if (cmd_tokens[1] == "deploy-module") {
                        path = "cmd_ghd_deploy_module";
                        warning_msg = "This will overwrite any existing module.";
                    } else {
                        path = "cmd_ghd_deploy_core";
                        warning_msg = "WARNING: You are about to redeploy the core. Upgrading the core could break compatibility in your application. If this fails the console might be rendered unusable and you will have to redeploy manually.";
                        cmd_tokens[2] = cmd_tokens[2] === undefined? "": cmd_tokens[2];
                    }
                    if (cmd_tokens[2] === undefined) {
                        exec_ajax_fn(console_base + "/" + path, complete_fn);
                        return;
                    }
                    var deploy_fn = function(input) {
                        if (yes_eval_fn(input, false)) {
                            exec_ajax_fn(console_base + "/" + path + "/" + cmd_tokens[2], complete_fn);
                        } else {
                            complete_fn();
                        }
                    };
                    if (cmd_tokens[2].substr(cmd_tokens[2].length - 1) !== "*") {
                        print_fn(warning_msg + "\nReally continue? [N/y]:");
                        input_fn(deploy_fn);
                    } else {
                        deploy_fn("y");
                    }
                    break;
                default:
                    print_fn("Action missing.\n");
                    complete_fn();
                }
                break;
            case "versions":
                exec_ajax_fn(console_base + "/cmd_versions", complete_fn);
                break;
            case "db":
                switch (cmd_tokens[1]) {
                case "sync":
                    exec_ajax_fn(console_base + "/cmd_sync", complete_fn);
                    break;
                case "purify":
                    print_fn("Important data could be deleted. Really continue? [y/N]:");
                    input_fn(function(input) {
                        if (yes_eval_fn(input, false)) {
                            exec_ajax_fn(console_base + "/cmd_purify", complete_fn);
                        } else {
                            complete_fn();
                        }
                    });
                    break;
                case "cull":
                    exec_ajax_fn(console_base + "/cmd_cull", complete_fn);
                    break;
                case "repair":
                    exec_ajax_fn(console_base + "/cmd_repair", complete_fn);
                    break;
                default:
                    print_fn("Unknown db action.\n");
                    complete_fn();
                    break;
                }
                break;
            case "locale":
                var action = cmd_tokens[1];
                if (action === undefined) {
                    exec_ajax_fn(console_base + "/cmd_locale", complete_fn);
                } else {
                    if (action == "export") {
                        window.open(console_base + "/cmd_locale/" + action + "/" + cmd_tokens[2]);
                        complete_fn();
                    } else if (action == "import") {
                        print_fn("Drag and drop the .po file onto this text to import it. (Requires HTML5 capable browser.)\n");
                        var drop_fn = function(event) {
                            event.preventDefault();
                            if (event.type !== "drop")
                                return;
                            get_file_upload_body_fn(event, 1, "po_file", function(body, boundary) {
                                if (body === null) {
                                    print_fn("Error: No files or incorrect number of files dropped.\n");
                                } else {
                                    var xhr = new XMLHttpRequest();
                                    xhr.open("POST", console_base + "/cmd_locale/import", false);
                                    xhr.setRequestHeader('content-type', 'multipart/form-data; boundary=' + boundary);
                                    xhr.send(body);
                                    print_fn(xhr.responseText + "\n");
                                    window.removeEventListener("dragover", drop_fn, false);
                                    window.removeEventListener("drop", drop_fn, false);
                                }
                                complete_fn();
                            });
                        };
                        window.addEventListener("dragover", drop_fn, false);
                        window.addEventListener("drop", drop_fn, false);
                    } else {
                        exec_ajax_fn(console_base + "/cmd_locale/" + action + "/" + cmd_tokens[2], complete_fn);
                    }
                }
                break;
            case "info":
                window.open(console_base + "/cmd_info");
                complete_fn();
                break;
            case "config":
                if (cmd_tokens[1] === undefined) {
                    print_fn("Missing argument 1: module name\n");
                    complete_fn();
                } else if (cmd_tokens[2] === undefined) {
                    exec_ajax_fn(console_base + "/cmd_config/" + cmd_tokens[1], complete_fn);
                } else if (cmd_tokens[3] === undefined) {
                    exec_ajax_fn(console_base + "/cmd_config/" + cmd_tokens[1] + "/" + cmd_tokens[2], complete_fn);
                } else if (cmd_tokens[4] !== undefined) {
                    print_fn("Too many arguments.\n");
                    complete_fn();
                } else {
                    var param = {
                        set: cmd_tokens[3],
                        local: "false"
                    };
                    exec_ajax_fn(console_base + "/cmd_config/" + cmd_tokens[1] + "/" + cmd_tokens[2], complete_fn, param);
                }
                break;
            case "session":
                if (cmd_tokens[1] !== "restart") {
                    print_fn("Unknown action.\n");
                    complete_fn();
                } else {
                    exec_ajax_fn(console_base + "/cmd_session_restart", complete_fn);
                }
                break;
            case "install":
                print_fn("Welcome to the melt (re)installation script. Melt needs a working MySQL 5.1+ database to function. Remember that you can use your normal copy/paste hotkeys.\n");
                var configure_db_fn = function(done_fn) {
                    print_fn("Do you wish to configure your MySQL details? [Y/n]");
                    input_fn(function(result) {
                        if (!yes_eval_fn(result, true)) {
                            done_fn();
                        } else {
                            mass_input_fn([
                                ["Database host", "127.0.0.1"],
                                ["Database port", "3306"],
                                ["Database username", "root"],
                                ["Database password", ""],
                                ["Database name", "melt"],
                                ["Database prefix", ""],
                                ["Do you have trigger permissions?", "Y/n"],
                                ["Do you have InnoDB?", "Y/n"]
                            ], function(result) {
                                print_fn("Configuring your installation, please wait.\n");
                                exec_fn("config db host " + cmd_line_escape_fn(result[0]));
                                exec_fn("config db port " + cmd_line_escape_fn(result[1]));
                                exec_fn("config db user " + cmd_line_escape_fn(result[2]));
                                exec_fn("config db password " + cmd_line_escape_fn(result[3]));
                                exec_fn("config db name " + cmd_line_escape_fn(result[4]));
                                exec_fn("config db prefix " + cmd_line_escape_fn(result[5]));
                                exec_fn("config db use_trigger_sequencing " + cmd_line_escape_fn(yes_eval_fn(result[6])? "true": "false"));
                                exec_fn("config db storage_engine " + cmd_line_escape_fn(yes_eval_fn(result[7])? "innodb": "myisam"));
                                done_fn();
                            });
                        }
                    });
                };
                var configure_mail_fn = function(done_fn) {
                    print_fn("Do you wish to configure your SMTP details? (Skip if your application won't send mail.) [Y/n]");
                    input_fn(function(result) {
                        if (!yes_eval_fn(result, true)) {
                            done_fn();
                        } else {
                            mass_input_fn([
                                ["SMTP name", "127.0.0.1"],
                                ["SMTP port", "3306"],
                                ["Does the SMTP server require TLS?", "N/y"],
                                ["Does the SMTP server require authentication?", "Y/n"],
                            ], function(result) {
                                exec_fn("config mail smtp_host " + cmd_line_escape_fn(result[0]));
                                exec_fn("config mail smtp_port " + cmd_line_escape_fn(result[1]));
                                exec_fn("config mail smtp_tls_enable " + cmd_line_escape_fn(yes_eval_fn(result[2])? "true": "false"));
                                exec_fn("config mail smtp_auth_enable " + cmd_line_escape_fn(yes_eval_fn(result[3])? "true": "false"));
                                if (yes_eval_fn(result[3])) {
                                    mass_input_fn([
                                        ["SMTP authentication username", ""],
                                        ["SMTP authentication password", ""],
                                    ], function(result) {
                                        exec_fn("config mail smtp_auth_user " + cmd_line_escape_fn(result[0]));
                                        exec_fn("config mail smtp_auth_password " + cmd_line_escape_fn(result[1]));
                                        done_fn();
                                    });
                                } else {
                                    done_fn();
                                }
                            });
                        }
                    });
                };
                var configure_app_fn = function(done_fn) {
                    print_fn("Do you wish to deploy the default sample application? (Skip if you already have an application installed.) [Y/n]");
                    input_fn(function(result) {
                        if (yes_eval_fn(result, true)) {
                            exec_fn("ghd deploy-sample-app melt/sample-app-default", done_fn);
                        } else {
                            done_fn();
                        }
                    });
                };
                var sync_db_fn = function(done_fn) {
                    print_fn("Do you wish to syncronize the database with the application now? [Y/n]");
                    input_fn(function(result) {
                        if (yes_eval_fn(result, true)) {
                            exec_fn("db sync", done_fn);
                        } else {
                            done_fn();
                        }
                    });
                };
                var done_fn = function() {
                    print_fn("Installation script complete.\n");
                    complete_fn();
                };
                configure_db_fn(function() {
                    configure_mail_fn(function() {
                        configure_app_fn(function() {
                            sync_db_fn(function() {
                                done_fn();
                            });
                        });
                    });
                });
                break;
            case "reload":
                window.location = window.location;
                complete_fn();
                break;
            case "logout":
                $.cookie('MELT_DEVKEY', null, {path: '/'});
                login_fn();
                break;
            case "xkcd":
                $.get(console_base + "/cmd_xkcd", {}, function(data) {
                    output_div.append(data);
                    print_fn("\n");
                    complete_fn();
                });
                break;
            default:
                print_fn("Unknown command \"" + cmd_tokens[0] + "\"\n");
                complete_fn();
            }
        };
        var tab_fn = function() {
            var changed_cmd_line = "";
            var inner_tab_fn = function(current_cmd, branch) {
                //current_cmd = current_cmd.replace(/^\s+/, "");
                //var cmd_tokens = current_cmd.match(/^([^\s]*)(.*)$/);
                var cmd_tokens;
                if (typeof(current_cmd) == "object") {
                    cmd_tokens = current_cmd;
                } else {
                    var cmd_line_tokens = get_cmd_line_tokens_fn(current_cmd);
                    cmd_tokens = cmd_line_tokens[0];
                    if (cmd_line_tokens[1])
                        cmd_tokens.push(true);
                }
                var cur_token = cmd_tokens[0] !== undefined && cmd_tokens[0] !== true? cmd_tokens[0]: "";
                var next_token = cmd_tokens[1] !== undefined? cmd_tokens[1]: "";
                if (next_token != "") {
                    $.each(branch, function(node, sub_branch) {
                        if (node != cur_token)
                            return;
                        changed_cmd_line += cmd_line_escape_fn(cur_token) + " ";
                        if (typeof(sub_branch) == "function")
                            sub_branch = sub_branch();
                        inner_tab_fn(cmd_tokens.slice(1), sub_branch);
                        return false;
                    });
                } else {
                    var matches = [];
                    $.each(branch, function(node) {
                        if (node.length < cur_token.length)
                            return;
                        if (node.substr(0, cur_token.length) === cur_token)
                            matches.push(node);
                    });
                    switch (matches.length) {
                    case 0:
                        break;
                    case 1:
                        changed_cmd_line += cmd_line_escape_fn(matches.pop()) + " ";
                        update_cmd_line_fn(changed_cmd_line);
                        break;
                    default:
                        var lcp = matches[0];
                        for (var i = 0; i < matches.length; i++) {
                            var this_match = matches[i];
                            for (var j = 0; j < lcp.length; j++) {
                                if (lcp.charAt(j) !== this_match.charAt(j)) {
                                    lcp = lcp.substr(0, j);
                                    break;
                                }
                            }
                            matches[i] = cmd_line_escape_fn(this_match);
                        }
                        if (lcp.length > 0) {
                            changed_cmd_line += cmd_line_escape_fn(lcp, true);
                            update_cmd_line_fn(changed_cmd_line);
                        }
                        print_fn(input_div.text() + "\n", color_command);
                        print_fn(matches.sort().join(" ") + "\n", color_tabhint);
                        break;
                    }
                }
            };
            inner_tab_fn(cmd_line, tab_tree);
        };
        $("body").empty().css({
            "background": "#000",
            "color": color_output,
            "margin": "5px",
            "font-family": "monospace",
            "white-space": "pre-wrap"
        }).append(output_div).append(input_div).append(input);
        $(window).mouseup(function() { input.focus(); });
        var update_timeout = null;
        input.blur(function() { return false; }).focus().keydown(function(event) {
            var keydown_fn = function() {
                update_timeout = null;
                if (input_pipe !== false) {
                    input_pipe(event.keyCode, input.val());
                } else {
                    switch (event.keyCode) {
                    case 0x08: // backspace
                        update_cmd_line_fn(cmd_line.substr(0, cmd_line.length - 1));
                        break;
                    case 0x09: // tab
                        tab_fn();
                        break;
                    case 0x0d: // return
                        exec_fn(cmd_line);
                        break;
                    case 38: // up
                    case 40: // down
                        if (cmd_history_at === null) {
                            cmd_history_current = cmd_line;
                            cmd_history_at = cmd_history.length;
                        }
                        if (cmd_history_at >= 0) {
                            if (event.keyCode == 38) {
                                if (cmd_history_at > 0) {
                                    cmd_history_at--;
                                    update_cmd_line_fn(cmd_history[cmd_history_at]);
                                }
                            } else {
                                if (cmd_history_at < cmd_history.length - 1) {
                                    cmd_history_at++;
                                    update_cmd_line_fn(cmd_history[cmd_history_at]);
                                } else {
                                    cmd_history_at = null;
                                    update_cmd_line_fn(cmd_history_current);
                                }
                            }
                        }
                        break;
                    default:
                        var value = input.val();
                        if (value == "")
                            return;
                        update_cmd_line_fn(cmd_line + value);
                        break;
                    }
                }
                input.val("");
            };
            if (update_timeout !== null) {
                window.clearTimeout(update_timeout);
                keydown_fn();
            }
            update_timeout = window.setTimeout(keydown_fn);
            if (event.keyCode == 0x09)
                return false;
        }).keyup(function() { input.focus(); });
        login_fn();


        /*
        var tab_tree = null;
        var tabbing = false;
        var tab_xhr = null;
        var tab_fn = function() {
            /*
            if (tab_xhr !== null)
                return;
            $.ajax({
                url: tab_tree_url,
                type: "get",
                async: true,
                success: function(data) {
                    tab_xhr = null;
                }
                complete: function() {
                }
            });
        };
        tab_fn();*/


    });
</script>