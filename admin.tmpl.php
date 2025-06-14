<?php
/**
 * @param array $options
 * @param bool $predefinedAwsKeys
 * @param string $flashHtml
 * @param array $senders
 */
?>
<div class="wrap wpses">
<h2><?php _e('WP SES Options', 'wpses') ?></h2>
<?= $flashHtml ?>
<div id="tabs" style="display:none">
    <ul>
        <li><a href="#tabs-1">General Settings</a></li>
        <li><a href="#tabs-2">Confirmed Senders</a></li>
        <li><a href="#tabs-3">Testing</a></li>
        <li><a href="#tabs-4">Logging</a></li>
        <li><a href="#tabs-5">Stats</a></li>
    </ul>
    <div id="tabs-1">
        <h2>Status</h2>
        <ul class="status">
            <?php
            if ($options['from_email'] != '') {
                ?><li class="success">Sender Email is set</li><?php
            } else {
                ?><li class="failure">Sender Email is not set</li><?php
            }

            if ($options['credentials_ok'] == 1) {
                ?><li class="success">API keys are valid</li><?php
            } else {
                ?><li class="failure">Amazon API Keys are not valid, or you did not finalize your Amazon SES registration</li><?php
            }

            if (($options['from_email'] != '') and ($senders[$options['from_email']][1])) {
                ?><li class="success">Sender Email has been confirmed</li><?php
            } else {
                ?><li class="failure">Sender Email has not been confirmed</li><?php
            }
            ?>	

            <?php
            if ($options['active'] == 1) {
                ?><li class="success">Emails are being delivered via SES</li>

                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <?php wp_nonce_field('wpses'); ?>
                    <p class="submit">
                        <input type="submit" name="deactivate" value="<?php _e('Disable SES Delivery', 'wpses') ?>" />
                        The site's emails be delivered by the default WordPress method while you test SES here.
                    </p>
                </form>
                <?php
            } else {
                ?><li class="failure">Emails are not being delivered via SES</li>
                <li>
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <?php wp_nonce_field('wpses'); ?>
                    <input  type="checkbox" name="force" value="1" /> &nbsp;
                    Ignore warnings and force enabling of SES. Check this if you use IAM credentials, have validated sender emails for the SES endpoint you are using, and the production email test is successful.
                    <p class="submit">
                        <input type="submit" name="activate" value="<?php _e('Enable SES Delivery', 'wpses') ?>" />
                    </p>
                    Only enable SES if your account is in production mode.
                </form>  
                </li>		
            <?php
            } ?>

        </ul>
        <h2><?php _e('Email Settings', 'wpses') ?></h2>
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <?php wp_nonce_field('wpses'); ?>
            <table class="form-table">
                <tr><th scope="row"><?php _e('Sender Email *', 'wpses') ?></th>
                    <td>
                        <?php if (!defined('WP_SES_FROM')) {
                ?>
                            <select name="from_email">
                                <option></option>
                                <?php foreach ($senders as $email => $props) {
                    printf("<option %s>%s</option>", $email == $options['from_email'] ? 'selected' : '', $email);
                } ?>
                            </select>
                            &nbsp;
                            <p style="margin:10px 0"><?php _e('Any <code>wp_mail_from</code> filters will be ignored.', 'wpses') ?></p>
                            <?php
            } else {
                echo WP_SES_FROM;
            }
                        ?>
                    </td>
                </tr>
                <tr><th scope="row"><?php _e('Sender Name', 'wpses') ?></th>
                    <td>
                        <input type="text" name="from_name" placeholder="Firstname Lastname" style="width:30%"  value="" />&nbsp;
                        <p style="margin:10px 0"><?php _e('This can be overridden by <code>wp_mail_from_name</code> filters.', 'wpses') ?></p>
                    </td>
                </tr>
                <tr><th scope="row"><?php _e('Return Path', 'wpses') ?></th>
                    <td>
                        <?php if (!defined('WP_SES_RETURNPATH')) {
                            ?>
                            <input type="text" name="return_path" placeholder="user@domain.com" style="width:30%" value="<?php echo $options['return_path']; ?>" />&nbsp;
                            <p style="margin:10px 0"><?php _e('Delivery Status notification messages will be sent to this address.', 'wpses') ?></p>
                            <?php
                        } else {
                            echo('(' . WP_SES_RETURNPATH . ') ');
                            _e('Return path was defined by your admin.', 'wp-ses');
                        }
                        ?>
                    </td></tr>
                <tr><th scope="row"><?php _e('Reply To', 'wpses') ?></th>
                    <td>
                        <?php if (!defined('WP_SES_REPLYTO') or ('' == WP_SES_REPLYTO)) {
                            ?>
                            <input type="text" name="reply_to" placeholder="user@domain.com" style="width:30%" value="<?php echo $options['reply_to']; ?>" />&nbsp;
                            <p style="margin:10px 0"><?php _e('Replies to your messages will be sent to this address.  Or, set to "headers" to extract Reply-to from email headers.', 'wpses') ?>
                            </p>
                            <?php
                        } else {
                            echo('(' . WP_SES_REPLYTO . ') ');
                            _e('Reply To was defined by your admin.', 'wp-ses');
                        }
                        ?>
                    </td></tr>
                <?php if (defined('WP_SES_RECIPIENT_EMAIL')) { ?>
                <tr><th scope="row"><?php _e('Recipient Email', 'wpses') ?></th>
                    <td>
                        <input type="text" name="recipient_email" readonly disabled style="width:30%"  value="<?= WP_SES_RECIPIENT_EMAIL ?>" />&nbsp;
                        <p style="margin:10px 0"><?php _e('All emails will be delivered to this address, which has been set in <code>wp-config.php</code>.', 'wpses') ?></p>
                    </td>
                </tr>
                <?php } ?>
                <tr>
                    <th scope="row"><?php _e('Email Body Template', 'wpses') ?></th>
                    <td>
                        <input type="text" name="email_body_template_path" placeholder="/path/to/template/file.php" value="<?php echo $options['email_body_template_path']; ?>" style="width:50%" />&nbsp;
                        <p style="margin:10px 0">
                            Enter the full path to a PHP template file, or a path relative to <code>ABSPATH</code>.  If blank, the HTML below will be used instead.
                            For either of these, use the following tokens:
                        <ol>
                            <li><code>{body}</code> to replace content.</li>
                            <li><code><?php echo htmlspecialchars('<!-- {preheader} -->') ?></code> will be populated from the inner contents of the HTML comment <code><?php echo htmlspecialchars('<!-- <PREHEADER>...</PREHEADER> -->') ?></code> in the email body, which will then be removed</li>
                        </ol>
                        </p>

                        <div style="border:1px solid #ccc;width:100%">
                            <textarea name="email_body_template_html" data-editor="html" style="width:100%;height:500px"><?php echo $options['email_body_template_html']; ?></textarea>
                        </div>
                    </td>
                </tr>
            </table>

            <h2><?php _e("AWS SES Keys", 'wpses') ?></h2>
            <?php if (!$predefinedAwsKeys) {
                            ?>
                <p>
                    <?php _e('Enter the keys provided by AWS below.', 'wpses') ?>
                </p>
                <table class="form-table" style="width:70%; float:left;" width="450">
                    <tr><th scope="row"><?php _e('Access Key *', 'wpses') ?></th>
                        <td><input type="text" name="access_key" value="<?php echo $options['access_key']; ?>" style="width:50%" /></td></tr>
                    <tr><th scope="row"><?php _e('Secret Key *', 'wpses') ?></th>
                        <td><input type="text" name="secret_key" value="<?php echo $options['secret_key']; ?>" style="width:50%" /></td></tr>

                    <tr><th scope="row"><?php _e('SES Endpoint', 'wpses') ?></th>
                        <td><select name="endpoint">
                                <option value="email.us-east-1.amazonaws.com" <?php
                                if ('email.us-east-1.amazonaws.com' == $options['endpoint']) {
                                    echo 'selected';
                                } ?>>US East (N. Virginia) Region</option>
                                <option value="email.us-west-2.amazonaws.com" <?php
                                if ('email.us-west-2.amazonaws.com' == $options['endpoint']) {
                                    echo 'selected';
                                } ?>>US West (Oregon) Region</option>
                                <option value="email.eu-west-1.amazonaws.com" <?php
                                if ('email.eu-west-1.amazonaws.com' == $options['endpoint']) {
                                    echo 'selected';
                                } ?>>EU (Ireland) Region</option>
                            </select>
                        </td></tr>
                    <tr><th scope="row">&nbsp;</th>
                        <td><?php _e('You\'ll need to validate sender emails for each Endpoint you want to use.', 'wpses') ?></td></tr>

                </table>
            <?php
                        } else { // restricted access?>
                Keys have been set in <code>wp-config.php</code>.
            <?php
                        } ?>
            <input type="hidden" name="action" value="update" />
            <p class="submit" style="clear:both">
                <input type="submit" name="save" value="<?php _e('Save Changes') ?>" />
            </p>
        </form>
    </div>
    <div id="tabs-2">

        <?php if (!defined('WP_SES_HIDE_VERIFIED') or (false == WP_SES_HIDE_VERIFIED)) {
                            ?>
        <p>The following confirmed senders are able to send an email via SES:</p>
        <table class="form-table">
            <tr style="background-color:#ccc; font-weight:bold;"><td><?php _e('Email', 'wpses') ?></td><td><?php _e('Confirmed', 'wpses') ?></td><td><?php _e('Action', 'wpses') ?></td></tr>
            <?php
            $i = 0;
                            foreach ($senders as $email => $props) {
                                printf("<tr %s>", $i % 2 == 0 ? 'style="background-color:#ddd"' : '');
                                echo("<td>$email</td>");
                                echo("<td>" . ($props[1] == 1 ? 'Yes' : 'No') . "</td>");
                                echo("<td>");
                                if ($props[1] and !$predefinedAwsKeys) { // remove this email?>
                <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    <?php wp_nonce_field('wpses'); ?><input type="hidden" name="email" value="<?= $email ?>">
                    <!-- div class="submit" -->
                    <input type="submit" name="removeemail" value="<?php _e('Remove', 'wpses') ?>" onclick="return confirm('Are you sure you want to remove <?= $email ?>?', 'wpses') ?>')"/>
                    <!-- /div -->
                </form>
                <?php
                                }
                                echo("</td></tr>");
                                $i++;
                            } ?>
        </table>
    <?php
                        } ?>
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <?php wp_nonce_field('wpses'); ?>
            <!-- todo : que si email defini, que si pas dans la liste  -->
            
            <p class="submit">
                <input type="submit" name="addemail" value="<?php _e('Add this Email', 'wpses') ?>" />&nbsp; <?php printf("Add the email <strong>%s</strong> to the confirmed senders.", $options['from_email']); ?>
            </p>
        </form>
    </div>

    <div id="tabs-3">
        <h2><?php _e('Test Email', 'wpses') ?></h2>

        <!-- todo: que si email expediteur valid? -->
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <?php wp_nonce_field('wpses'); ?>
            <p class="submit">
                <input type="submit" name="testemail" value="<?php _e("Send Test Email", 'wpses') ?>" /> &nbsp;  This will send a test email via SES to <strong><?= defined('WP_SES_RECIPIENT_EMAIL') ? WP_SES_RECIPIENT_EMAIL : $options['from_email'] ?></strong>.
            </p>
        </form>

        <h2><?php _e('Test Production Email', 'wpses') ?></h2>
        <?php _e('Use the form below to test sending an email to any address, once production mode is activated.', 'wpses') ?>
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <?php wp_nonce_field('wpses'); ?>
            <table class="form-table" >
                <tr>
                    <th scope="row"><?php _e('Recipient Email *', 'wpses') ?></th>
                        <?php 
                            $attributes = 'value=""';
                            if (defined('WP_SES_RECIPIENT_EMAIL'))
                                $attributes = sprintf('value="%s" readonly', WP_SES_RECIPIENT_EMAIL);
                        ?>
                    <td><input type="text" name="prod_email_to" <?= $attributes ?> placeholder="name@domain.com" /></td>
                </tr>
                <tr><th scope="row"><?php _e('Subject *', 'wpses') ?></th>
                    <td><input type="text" name="prod_email_subject" value="Email from WP-SES" placeholder="Email subject" /></td></tr>
                <tr><th scope="row"><?php _e('Body *', 'wpses') ?></th>
                    <td><textarea cols="80" rows="5" name="prod_email_content" placeholder="Text goes here">Text goes here</textarea></td></tr>
                <tr><th scope="row"><?php _e('Include an attachment to send via SendRawEmail?', 'wpses') ?></th>
                    <td><input type="checkbox" name="prod_email_attachment" /></td></tr>
            </table>
            <p class="submit">
                <input type="submit" name="prodemail" value="<?php _e("Send Production Email", 'wpses') ?>" />
            </p>
        </form>
    </div>

    <div id="tabs-4">
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <?php wp_nonce_field('wpses'); ?>
            <?php if ($options['log']) {
                            ?>
                <p>
                Logging is enabled.  <a href="?wpses_viewlogs=1"" target="_blank">View Logs</a>.
                </p>
                <input type="submit" name="deactivatelogs" value="<?php _e('Disable', 'wpses') ?>" />
            <?php
                        } else {
                            ?>
                <p>
                <?php _e('Logging is disabled.', 'wpses') ?>
                </p>
                <input type="submit" name="activatelogs" value="<?php _e('Enable', 'wpses') ?>" />
            <?php
                        } ?>
        </form> 
    </div>

    <div id="tabs-5">
    </div>
</div>

<?php
// Push these to the footer
add_action('admin_print_footer_scripts', function () use ($options) {
    ?>
    <script type="text/javascript">
        jQuery(function ($) {

            // This is the only way to get the quote-encoding correct
            $('input[name=from_name]').val(<?= json_encode($options['from_name']); ?>);

            // Progressive loading, modified from https://gist.github.com/duncansmart/5267653
            $('textarea[data-editor]').each(function () {
                var textarea = $(this);
                var mode = textarea.data('editor');
                var editDiv = $('<div>', {
                    position: 'absolute',
                    width: '100%',
                    height: textarea.height(),
                    'class': textarea.attr('class')
                }).insertBefore(textarea);
                textarea.css('display', 'none');
                var editor = ace.edit(editDiv[0]);
                editor.renderer.setShowGutter(true);
                editor.getSession().setValue(textarea.val());
                editor.getSession().setMode("ace/mode/" + mode);

                // copy back to textarea on form submit...
                textarea.closest('form').submit(function () {
                    textarea.val(editor.getSession().getValue());
                })
            });

            $('input[name=email_body_template_path]').on('blur', function () {
                var $row = $('textarea[name=email_body_template_html]').closest('div');
                if ($(this).val() == '')
                {
                    $row.slideDown();
                } else {
                    $row.slideUp();
                }
            }).blur();

            $( "#tabs" ).show().tabs({
                activate: function( event, ui ) {
                    if (ui.newPanel.attr('id') == 'tabs-5')
                    {               
                        ui.newPanel.html('Loading... <img src="/wp-admin/images/loading.gif"/>');    
                        var data = {
                            'action': 'wpses_stats'
                        };

                        // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                        jQuery.post(ajaxurl, data, function(response) {
                            ui.newPanel.html(response);
                        });
                    }
                }
            });

            $('.wpses li.success').prepend('<span class="dashicons dashicons-yes"></span>');
            $('.wpses li.failure').prepend('<span class="dashicons dashicons-no"></span>');
        });
    </script>
    <?php
});
