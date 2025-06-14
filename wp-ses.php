<?php

/*
  Plugin Name: WP SES
  Version: 1.1.0
  Plugin URI: https://github.com/rkaiser0324/wp-ses
  Description: Uses Amazon SES for all outgoing WordPress emails.  Forked from SylvainDeaure's WP SES.
  Author: DigiPowers, Inc.
  Author URI: https://www.digipowers.com
 */

class WpSes
{
    private static $_predefinedAwsKeys = false;
    private static $_SES;
    public static $_options;
    private static $_flashMessages = [];

    public static function initialize()
    {
        try {
            foreach (['SimpleEmailService.php', 'SimpleEmailServiceRequest.php', 'SimpleEmailServiceMessage.php'] as $file)
            {
                require_once(dirname(__FILE__) . '/lib/' . $file);
            }

            // Support alternate key names used by WP Offload S3
            if (!defined('WP_SES_ACCESS_KEY') && defined('AWS_ACCESS_KEY_ID')) {
                define('WP_SES_ACCESS_KEY', AWS_ACCESS_KEY_ID);
            }
            if (!defined('WP_SES_SECRET_KEY') && defined('AWS_SECRET_ACCESS_KEY')) {
                define('WP_SES_SECRET_KEY', AWS_SECRET_ACCESS_KEY);
            }

            self::$_predefinedAwsKeys = defined('WP_SES_ACCESS_KEY') && defined('WP_SES_SECRET_KEY');
        
            self::_getOptions();

            if (is_admin()) {
                self::_adminLoad();
            }

            if (!function_exists('wp_mail')) {
                function wp_mail($to, $subject, $message, $headers = '', $attachments = '')
                {
                    $id = WpSes::sendSesEmail($to, $subject, $message, $headers, $attachments);
                    return (!empty($id));
                }
            } else {
                self::_log("ERROR\twp_mail override by another plugin.  Disabling.");

                self::$_options['active'] = 0;
                update_option('wpses_options', self::$_options);

                $func = new ReflectionFunction('wp_mail');
                throw new exception(sprintf("The <code>wp_mail()</code> function is being overridden in <code>%s</code>. You'll need to remove that before WP SES can be enabled.", $func->getFileName()));
            }
            
            add_filter('wp_mail_from_name', function ($name) {
                if (!empty(self::$_options['from_name'])) {
                    $name = self::$_options['from_name'];
                }
                return $name;
            }, 1);

            self::$_SES = new SimpleEmailService(self::$_options['access_key'], self::$_options['secret_key'], self::$_options['endpoint'], true, SimpleEmailService::REQUEST_SIGNATURE_V4);
        } catch (exception $ex) {
            add_action('admin_notices', function () use ($ex) {
                printf("<div class='error fade'><p>%s</p></div>", $ex->getMessage());
            });
        }
    }

    private static function _getOptions()
    {
        self::$_options = get_option('wpses_options');
        if (!is_array(self::$_options)) {
            self::$_options = array();
        }

        if (!array_key_exists('email_body_template_path', self::$_options)) {
            self::$_options['email_body_template_path'] = '';
        }
        if (!array_key_exists('email_body_template_html', self::$_options)) {
            self::$_options['email_body_template_html'] = '{body}';
        }

        // SES parameters that may be hardcoded into wp-config.php
        if (defined('WP_SES_REPLYTO')) {
            self::$_options['reply_to'] = WP_SES_REPLYTO;
        }
        if (empty(self::$_options['reply_to'])) {
            self::$_options['reply_to'] = '';
        }
        if (defined('WP_SES_RETURNPATH')) {
            self::$_options['return_path'] = WP_SES_RETURNPATH;
        }
        if (empty(self::$_options['return_path'])) {
            self::$_options['return_path'] = '';
        }
        if (defined('WP_SES_FROM')) {
            self::$_options['from_email'] = WP_SES_FROM;
        }
        if (empty(self::$_options['from_email'])) {
            self::$_options['from_email'] = '';
        }
        if (defined('WP_SES_ENDPOINT')) {
            self::$_options['endpoint'] = WP_SES_ENDPOINT;
        }
        if (empty(self::$_options['endpoint'])) {
            self::$_options['endpoint'] = 'email.us-east-1.amazonaws.com';
        }
        if (defined('WP_SES_ACCESS_KEY')) {
            self::$_options['access_key'] = WP_SES_ACCESS_KEY;
        }
        if (empty(self::$_options['access_key'])) {
            self::$_options['access_key'] = '';
        }
        if (defined('WP_SES_SECRET_KEY')) {
            self::$_options['secret_key'] = WP_SES_SECRET_KEY;
        }
        if (empty(self::$_options['secret_key'])) {
            self::$_options['secret_key'] = '';
        }

        // Other plugin settings
        if (empty(self::$_options['force'])) {
            self::$_options['force'] = 0;
        }
        if (empty(self::$_options['log'])) {
            self::$_options['log'] = 0;
        }
        if (empty(self::$_options['from_name'])) {
            self::$_options['from_name'] = '';
        }
        if (empty(self::$_options['sender_ok'])) {
            self::$_options['sender_ok'] = 0;
        }
        if (empty(self::$_options['credentials_ok'])) {
            self::$_options['credentials_ok'] = 0;
        }

        // Not sure if this is actually in use
        if (defined('WP_SES_AUTOACTIVATE') && WP_SES_AUTOACTIVATE) {
            self::$_options['active'] = 1;
        }
    }

    private static function _adminLoad()
    {
        add_action('init', function () {
            if (!function_exists('curl_version')) {
                add_action('admin_notices', function () {
                    echo "<div class='error fade'><p><strong>" . __("cURL extension is not installed. WP SES won't work without it.", 'wpses') . "</strong></p></div>";
                });
            }
        });

        if (preg_match('/page=wp_ses/', $_SERVER['REQUEST_URI'])) {
            add_action('admin_enqueue_scripts', function () {
                wp_enqueue_script('jquery-ui-tabs');
                wp_enqueue_style('jquery-ui-tabs', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.1/themes/smoothness/jquery-ui.css');
                wp_enqueue_script('wp-ses-ace', "https://cdnjs.cloudflare.com/ajax/libs/ace/1.2.6/ace.js", null, null, true);
            });

            add_action('admin_head', function () {
                ?>
<style>
    .wpses .success {
        color: green
    }

    .wpses .failure {
        color: red
    }
</style>
<?php
            });
        }

        add_action('admin_menu', function () {
            add_options_page('wpses', __('WP SES', 'wpses'), 'manage_options', 'wp_ses', array(__CLASS__, 'optionsPage'));
        });

        register_activation_hook(__FILE__, function () {
            if (!get_option('wpses_options')) {
                add_option('wpses_options', array(
                'from_email' => '',
                'return_path' => '',
                'from_name' => 'WordPress',
                'access_key' => '',
                'secret_key' => '',
                'endpoint' => 'email.us-east-1.amazonaws.com',
                'credentials_ok' => 0,
                'sender_ok' => 0,
                'last_ses_check' => 0, // timestamp of last quota check
                'force' => 0,
                'log' => 0,
                'active' => 1, // reset to 0 if not pluggable or config change.
                'version' => '0' // Version of the db
                    // TODO: garder liste des ids des demandes associ�es � chaque email.
                    // afficher : email, id demande , valid� ?
            ));
                self::_getOptions();
            }
        });

        register_deactivation_hook(__FILE__, function () {
            // TODO
            // delete_option('wpses_options');
            // Do not delete, else we loose the version number
            // TODO: add an uninstall link ? Not a big deal since we added very little overhead
        });

        if (!empty($_GET['wpses_viewlogs'])) {
            $path = dirname(ini_get('error_log')) . "/wpses.log";
            if (file_exists($path)) {
                echo nl2br(file_get_contents($path));
            }
            wp_die();
        }
        
        add_action('wp_ajax_wpses_stats', function () {
            // TODO: add 15 min cache to stats
            // TODO: add chart

            try {

                // Convert errors thrown by SimpleEmailService to exceptions
                set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                    throw new exception($errstr);
                });
    
                if (self::$_options['credentials_ok'] != 1) {
                    throw new exception('Amazon API credentials have not been checked.');
                }
    
                $quota = self::$_SES->getSendQuota();
            
                $quota['SendRemaining'] = $quota['Max24HourSend'] - $quota['SentLast24Hours'];
                if ($quota['Max24HourSend'] > 0) {
                    $quota['SendUsage'] = round($quota['SentLast24Hours'] * 100 / $quota['Max24HourSend']);
                } else {
                    $quota['SendUsage'] = 0;
                }
    
                $stats = self::$_SES->getSendStatistics();
                usort($stats['SendDataPoints'], function ($a, $b) {
                    if ($a['Timestamp'] < $b['Timestamp']) {
                        return -1;
                    }
                    return 1;
                });
            } catch (exception $ex) {
                self::_addFlashMessage($ex->getMessage(), 'error');
            }
            restore_error_handler();
            echo implode('', self::$_flashMessages);
            include('stats.tmpl.php');
            wp_die(); // this is required to terminate immediately and return a proper response
        });
    }
 
    public static function optionsPage()
    {
        try {
            if (!current_user_can('administrator')) {
                throw new exception("Only administrators have access to this page.");
            }
            $verified_senders_arr = '';
            if ((self::$_options['access_key'] != '') and (self::$_options['secret_key'] != '')) {
                $verified_senders_arr = self::_getVerifiedSenders(!empty($_GET['wpses_refreshsenders']));
            }
            $senders = (array) get_option('wpses_senders');
            // ajouter dans senders les verified absents
            $updated = false;
            if ('' != $verified_senders_arr) {
                if (!is_array($verified_senders_arr)) {
                    $authorized = array($verified_senders_arr);
                }
                foreach ($verified_senders_arr as $email) {
                    if (!array_key_exists($email, $senders)) {
                        $senders[$email] = array(
                        -1,
                        true
                    );
                        $updated = true;
                    } else {
                        if (!$senders[$email][1]) {
                            // activer ceux qu'on a reçu depuis
                            $senders[$email][1] = true;
                            $updated = true;
                        }
                    }
                }
                // remove old senders
                foreach ($senders as $email => $info) {
                    if (is_array($email) and $info[1] and ! in_array($email, $verified_senders_arr)) {
                        $senders[$email][1] = false;
                        // echo 'remove '.$email.' ';
                        $updated = true;
                    }
                }
            }

            if ($updated) {
                update_option('wpses_senders', $senders);
            }
            if (((self::$_options['sender_ok'] != 1) and (self::$_options['force'] != 1)) or (self::$_options['credentials_ok'] != 1)) {
                self::$_options['active'] = 0;
                self::_log('Deactivate sender_ok=' . self::$_options['sender_ok'] . ' Force=' . self::$_options['force'] . ' credentials_ok=' . self::$_options['credentials_ok']);
                update_option('wpses_options', self::$_options);
            }
            if ((self::$_options['from_email'] != '')) {
                if (!isset($senders[self::$_options['from_email']])) {
                    $senders[self::$_options['from_email']] = array(-1, false);
                }
                if ($senders[self::$_options['from_email']][1] === true) { //
                    // email exp enregistré non vide et listé, on peut donc supposer que credentials ok et exp ok.
                    if (self::$_options['credentials_ok'] == 0) {
                        self::$_options['credentials_ok'] = 1;
                        self::_log('Credentials ok');
                        update_option('wpses_options', self::$_options);
                    }
                    if (self::$_options['sender_ok'] == 0) {
                        self::$_options['sender_ok'] = 1;
                        self::_log('Sender Ok');
                        update_option('wpses_options', self::$_options);
                    }
                } else {
                    //if ($senders[self::$_options['from_email']][1] !== TRUE) { //
                    self::$_options['sender_ok'] = 0;
                    self::_log('Sender not OK');
                    update_option('wpses_options', self::$_options);
                }
            }

            if (!empty($_POST['activate'])) {
                self::$_options['force'] = 0;
                if (!empty($_POST['force'])) {
                    // bad hack to force plugin activation with IAM credentials
                    self::$_options['sender_ok'] == 1;
                    self::$_options['force'] = 1;
                    self::_log('Forced activation');
                }
                if ((self::$_options['sender_ok'] == 1) and (self::$_options['credentials_ok'] == 1)) {
                    self::$_options['active'] = 1;
                    self::_log('Normal activation');
                    update_option('wpses_options', self::$_options);
                    echo '<div id="message" class="updated fade">
							<p>' . __('SES delivery has been enabled.', 'wpses') . '</p>
							</div>' . "\n";
                }
            }
            if (!empty($_POST['deactivate'])) {
                self::$_options['active'] = 0;
                self::_log('Manual deactivation');
                update_option('wpses_options', self::$_options);
                echo '<div id="message" class="updated fade">
							<p>' . __('SES delivery has been disabled.', 'wpses') . '</p>
							</div>' . "\n";
            }
            if (!empty($_POST['activatelogs'])) {
                self::$_options['log'] = 1;
                update_option('wpses_options', self::$_options);
                self::_log('Start Logging');
                self::_addFlashMessage("Logging has been enabled.");
            }
            if (!empty($_POST['deactivatelogs'])) {
                self::$_options['log'] = 0;
                update_option('wpses_options', self::$_options);
                self::_addFlashMessage("Logging has been disabled.");
            }
            if (!empty($_POST['save'])) {
                //check_admin_referer();
                //self::$_options['active'] = trim($_POST['active']);
                if (self::$_options['from_email'] != trim($_POST['from_email'])) {
                    self::_log('From Email changed, reset state');
                    self::$_options['sender_ok'] = 0;
                    self::$_options['active'] = 0;
                }
                if (!defined('WP_SES_FROM')) {
                    self::$_options['from_email'] = trim($_POST['from_email']);
                }
                if (!defined('WP_SES_RETURNPATH')) {
                    self::$_options['return_path'] = trim($_POST['return_path']);
                }
                if (self::$_options['return_path'] == '') {
                    self::$_options['return_path'] = self::$_options['from_email'];
                }
                if (!defined('WP_SES_REPLYTO')) {
                    self::$_options['reply_to'] = trim($_POST['reply_to']);
                    if (self::$_options['reply_to'] == '') {
                        self::$_options['reply_to'] = self::$_options['from_email'];
                    }
                }
                self::$_options['email_body_template_path'] = trim($_POST['email_body_template_path']);
                self::$_options['email_body_template_html'] = trim(stripslashes($_POST['email_body_template_html']));
                self::$_options['from_name'] = trim(stripslashes($_POST['from_name']));

                if (!self::$_predefinedAwsKeys) {
                    if ((self::$_options['access_key'] != trim($_POST['access_key'])) or (self::$_options['secret_key'] != trim($_POST['secret_key']))) {
                        self::_log('API Keys changed, reset state and disable');
                        self::$_options['credentials_ok'] = 0;
                        self::$_options['sender_ok'] = 0;
                        self::$_options['active'] = 0;
                        self::$_options['access_key'] = trim($_POST['access_key']);
                        self::$_options['secret_key'] = trim($_POST['secret_key']);
                        self::$_options['endpoint'] = trim($_POST['endpoint']);
                    }
                }

                update_option('wpses_options', self::$_options);
                self::_addFlashMessage("The settings were updated.");
            }
            self::_getOptions();
            //self::$_options = get_option('wpses_options');
            // validation cle amazon

            // validation email envoi
            if (!empty($_POST['addemail'])) {
                self::_verifySenderStep1(self::$_options['from_email']);
            }
            // remove verified email
            if (!empty($_POST['removeemail'])) {
                self::_removeSender($_POST['email']);
            }
            // envoi mail test
            if (!empty($_POST['testemail'])) {
                self::_log('Test email request');
                self::_testEmail(self::$_options['from_email']);
            }
            // envoi mail test prod
            if (!empty($_POST['prodemail'])) {
                self::_log('Prod email request');
                self::_testProductionEmail(
                    $_POST['prod_email_to'],
                    $_POST['prod_email_subject'],
                    $_POST['prod_email_content'],
                    empty($_POST['prod_email_attachment']) ? '' : $_POST['prod_email_attachment']
                );
            }
                   
            if (self::$_options['active'] != 1) {
                throw new exception('WP SES is not enabled.');
            }
        } catch (exception $ex) {
            self::_addFlashMessage($ex->getMessage(), 'error');
        }

        $options = self::$_options;
        $predefinedAwsKeys = self::$_predefinedAwsKeys;
        $flashHtml = implode('', self::$_flashMessages);
        include('admin.tmpl.php');
    }

    /**
     * Get the array of sending email addresses.
     *
     * @param bool $cache       Default true
     * @return array $senders
     */
    private static function _getVerifiedSenders($cache = true)
    {
        // TODO implement caching
        $cache = false;
        $key = 'wpses_senders';
        // Get any existing copy of our transient data.  In debug mode, skip the cache
        if (!$cache || false === ($senders = get_transient($key))) {
            $senders = null;
            $result = self::$_SES->listVerifiedEmailAddresses();
            if (is_array($result)) {
                $senders = $result['Addresses'];
                set_transient($key, $senders, 60 * 60);
            }
        }
        return $senders;
    }

    private static function _log($message)
    {
        if (self::$_options['log']) {
            $path = dirname(ini_get('error_log')) . "/wpses.log";
            error_log(time() . "\t" . $message . "\r\n", 3, $path);
        }
    }

    // start email verification (mail from amazon to sender, requesting validation)
    private static function _verifySenderStep1($mail)
    {
        // dans la chaine : Sender - InvalidClientTokenId  si auth pas correct
        // Sender - OptInRequired
        // The AWS Access Key Id needs a subscription for the service: si cl� aws ok, mais pas d'abo au service amazon lors de la verif d'un mail
        // inscription depuis aws , verif phone.

        $rid = self::$_SES->verifyEmailAddress($mail);
        $senders = get_option('wpses_senders');
        if ($rid <> '') {
            $senders[$mail] = array(
                $rid['RequestId'],
                false
            );
            self::$_options['credentials_ok'] = 1;
            self::_log('credentials_ok, sender verified');
            update_option('wpses_options', self::$_options);
            update_option('wpses_senders', $senders);
        }

        self::_addFlashMessage("A confirmation request has been sent. You will receive at the stated email a confirmation request from amazon SES. You MUST click on the provided link in order to confirm your sender Email. " . print_r($rid, true));
    }

    private static function _removeSender($mail)
    {
        $rid = self::$_SES->deleteVerifiedEmailAddress($mail);
        self::_addFlashMessage("This email address <strong>$mail</strong> has been removed from the list of verified senders.");
    }

    private static function _testEmail($mail)
    {
        $rid = self::sendSesEmail(
            self::$_options['from_email'],
            __('WP SES - Test Message', 'wpses'),
            __(
                "This is WP SES Test message. It has been sent via Amazon SES Service.\nAll looks fine !\n\n",
                'wpses'
            )
        );
        self::_addFlashMessage("Test message has been sent with ID $rid");
    }

    private static function _testProductionEmail($mail, $subject, $content, $add_attachment)
    {
        $attachments = $add_attachment == 'on' ? [dirname(__FILE__) . '/ses-logo.jpg'] : [];
        $rid = self::sendSesEmail($mail, $subject, $content, [], $attachments);
        self::_addFlashMessage("Test message has been sent with ID $rid");
    }

    /**
     * @param return int $reference_id
     */
    public static function sendSesEmail($to, $subject, $message, $headers = '', $attachments = '')
    {
        // headers can be sent as array, too. convert them to string to avoid further complications.
        if (is_array($headers)) {
            $headers = implode("\r\n", $headers);
        }
        if (is_array($to)) {
            $to = implode(",", $to);
        }
        extract(apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers')));

        $recipients = [
            'to' => $to
        ];

        /**
         * Note that the To, CC, and Bcc fields only support comma-separated expressions, e.g.,
         *  CC: user1@domain.com,user2@domain.com,...
         * 
         * Semicolons are not supported.
         */
        if (preg_match_all('/^CC: (.+)$/imsU', $headers, $address)) {
            $recipients['cc'] = $address[1][0];
        }

        if (preg_match_all('/^Bcc: (.+)$/imsU', $headers, $address)) {
            $recipients['bcc'] = $address[1][0];
        }

        $m = new SimpleEmailServiceMessage();

        // If WP_SES_RECIPIENT_EMAIL is set, that overrides everything.
        // Inspired by https://www.shawnhooper.ca/2015/04/21/redirect-development-environment-email/
        if (defined('WP_SES_RECIPIENT_EMAIL')) {
            $recipients_html = '';
            foreach ($recipients as $k => $v) {
                $recipients_html .= sprintf("<li><strong>%s</strong>: %s</li>", $k, $v);
            }
            $message = '<div style="padding:0.5em; background-color:#eee;border-color:1px solid #dcdcdc">This message would have been sent to these recipients: <ul>' . $recipients_html . '</ul></p></div>' . $message;
            $to = WP_SES_RECIPIENT_EMAIL;
        } else {
            if (!empty($recipients['cc'])) {
                if (preg_match('/,/im', $recipients['cc'])) {
                    foreach (explode(',', $recipients['cc']) as $el) {
                        $m->addCC($el);
                    }
                } else {
                    $m->addCC($recipients['cc']);
                }
            }
            if (!empty($recipients['bcc'])) {
                if (preg_match('/,/im', $recipients['bcc'])) {
                    foreach (explode(',', $recipients['bcc']) as $el) {
                        $m->addBCC($el);
                    }
                } else {
                    $m->addBCC($recipients['bcc']);
                }
            }
        }
         
        // what to do if more than 50 ? (SES limit)
        if (preg_match('/,/im', $to)) {
            $to = explode(',', $to);
            foreach ($to as $toline) {
                $m->addTo($toline);
            }
        } else {
            $m->addTo($to);
        }

        WpSes::_log('self::sendSesEmail ' . $to . "\t" . $headers);

        $txt = wp_specialchars_decode($message, ENT_QUOTES);

        $template_html = empty(WpSes::$_options['email_body_template_html']) ? '{body}' : WpSes::$_options['email_body_template_html'];
        $template_path = WpSes::$_options['email_body_template_path'];
        if (!empty($template_path))
        {
            if (!file_exists($template_path))
            {
                // Try a path relative to ABSPATH
                if (file_exists(ABSPATH . '/' . $template_path))
                {
                    $template_path = ABSPATH . '/' . $template_path;
                }
                else
                {
                    throw new exception(sprintf('The template path "%s" cannot be found.', WpSes::$_options['email_body_template_path']));
                }
            }
            ob_start();
            require $template_path;
            $template_html = ob_get_clean();
        }

        $html = str_replace("{body}", $message, $template_html);
        if (preg_match('@<!-- <PREHEADER>(.+)</PREHEADER> -->@msiU', $html, $matches)) {
            $html = preg_replace('@<!-- <PREHEADER>(.+)</PREHEADER> -->@msiU', '', $html);
            $html = str_replace("<!-- {preheader} -->", $matches[1], $html);
        }

        $m->setReturnPath(WpSes::$_options['return_path']);

        $from = apply_filters('wp_mail_from_name', WpSes::$_options['from_name']) . ' <' . WpSes::$_options['from_email'] . '>';
        if ('' != WpSes::$_options['reply_to']) {
            if ('headers' == strtolower(WpSes::$_options['reply_to'])) {
                // extract replyto from headers
                $rto = array();
                if (preg_match('/^Reply-To: ([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4})\b/imsU', $headers, $rto)) {
                    // does only support one email for now.
                    $m->addReplyTo($rto[1]);
                }
            } else {
                $m->addReplyTo(WpSes::$_options['reply_to']);
            }
        }

        $m->setFrom($from);
        $m->setSubject($subject);
        if ($html == '') { // que texte
            $m->setMessageFromString($txt);
        } else {
            $m->setMessageFromString($txt, $html);
        }
        // Attachments
        if ('' != $attachments) {
            if (!is_array($attachments)) {
                $attachments = explode("\n", $attachments);
            }
            // Now we got an array
            foreach ($attachments as $afile) {
                if (!$m->addAttachmentFromFile(basename($afile), $afile)) {
                    throw new exception($afile . ' could not be attached');
                }
            }
        }

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new exception($errstr);
        });
        $res = WpSes::$_SES->sendEmail($m);
        restore_error_handler();

        if (!is_array($res)) {
            throw new exception("WP SES could not send email. " . print_r($res, true));
        }
        WpSes::_log('SES id=' . $res['MessageId']);
        return $res['MessageId'];
    }

    private static function _addFlashMessage($message, $type = 'updated')
    {
        self::$_flashMessages[] = sprintf('<div class="%s fade"><p>%s</p></div>', $type, $message);
    }
}

WpSes::initialize();
