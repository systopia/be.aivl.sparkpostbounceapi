<?php
/*-------------------------------------------------------+
| SYSTOPIA Mailingtools Extension                        |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: P. Batroff (batroff@systopia.de)               |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Sparkpostbounceapi_ExtensionUtil as E;



class CRM_Sparkpostbounceapi_BounceEvent {

  // Constants shamelessly stolen from com.cividesk.email.sparkpost CRM/Sparkpost/Page/callback.php

  // Yes, dirty ... but there is no pseudoconstant function and CRM_Mailing_BAO_BouncePattern is useless
  public static $civicrm_bounce_types = [
    'Away' => 2,    // soft, retry 30 times
    'Relay' => 9,   // soft, retry 3 times
    'Invalid' => 6, // hard, retry 1 time
    'Spam' => 10,   // hard, retry 1 time
    ];

  // Source: https://support.sparkpost.com/customer/portal/articles/1929896
  public static $sparkpost_bounce_types = array(
    // Name, Description, Category, CiviCRM equivalent (see above)
    1 => array('Undetermined','The response text could not be identified.','Undetermined', ''),
    10 => array('Invalid Recipient','The recipient is invalid.','Hard', 'Invalid'),
    20 => array('Soft Bounce','The message soft bounced.','Soft', 'Relay'),
    21 => array('DNS Failure','The message bounced due to a DNS failure.','Soft', 'Relay'),
    22 => array('Mailbox Full','The message bounced due to the remote mailbox being over quota.','Soft', 'Away'),
    23 => array('Too Large','The message bounced because it was too large for the recipient.','Soft', 'Away'),
    24 => array('Timeout','The message timed out.','Soft', 'Relay'),
    25 => array('Admin Failure','The message was failed by Momentum\'s configured policies.','Admin', 'Spam'),
    30 => array('Generic Bounce: No RCPT','No recipient could be determined for the message.','Hard', 'Invalid'),
    40 => array('Generic Bounce','The message failed for unspecified reasons.','Soft', 'Relay'),
    50 => array('Mail Block','The message was blocked by the receiver.','Block', 'Spam'),
    51 => array('Spam Block','The message was blocked by the receiver as coming from a known spam source.','Block', 'Spam'),
    52 => array('Spam Content','The message was blocked by the receiver as spam.','Block', 'Spam'),
    53 => array('Prohibited Attachment','The message was blocked by the receiver because it contained an attachment.','Block', 'Spam'),
    54 => array('Relaying Denied','The message was blocked by the receiver because relaying is not allowed.','Block', 'Relay'),
    60 => array('Auto-Reply','The message is an auto-reply/vacation mail.','Soft', 'Away'),
    70 => array('Transient Failure','Message transmission has been temporarily delayed.','Soft', 'Away'),
    80 => array('Subscribe','The message is a subscribe request.','Admin', ''),
    90 => array('Unsubscribe','The message is an unsubscribe request.','Hard', 'Spam'),
    100 => array('Challenge-Response','The message is a challenge-response probe.','Soft', ''),
  );

  private $type;
  private $campaign_id;
  private $recipient;
  private $raw_reason;
  private $reason;
  private $bounce_class;


  public function __construct($type, $campaign_id, $recipient, $raw_reason, $reason, $bounce_class) {
    $this->type = $type;
    $this->campaign_id = $campaign_id;
    $this->recipient = $recipient;
    $this->raw_reason = $raw_reason;
    $this->reason = $reason;
    $this->bounce_class = $bounce_class;
  }

  public function process() {
    // verify data
    $this->sanity_check();

    // Extract CiviMail parameters from header value
    $dao             = new CRM_Core_DAO_MailSettings;
    $dao->domain_id  = CRM_Core_Config::domainID();
    $dao->is_default = TRUE;
    if ( $dao->find(true) ) {
      $rpRegex = '/^' . preg_quote($dao->localpart) . '(b|c|e|m|o|r|u)\.(\d+)\.(\d+)\.([0-9a-f]{16})/';
    } else {
      $rpRegex = '/^(b|c|e|m|o|r|u)\.(\d+)\.(\d+)\.([0-9a-f]{16})/';
    }
    $matches = array();
    if (preg_match($rpRegex, $this->recipient, $matches)) {
      list($match, $action, $job_id, $event_queue_id, $hash) = $matches;

      $params = array(
        'job_id' => $job_id,
        'event_queue_id' => $event_queue_id,
        'hash' => $hash,
      );

      // Was SparkPost able to classify the message?
      if (in_array($this->type, array(
        'spam_complaint',
        'policy_rejection'
      ))) {
        $params['bounce_type_id'] = CRM_Utils_Array::value('Spam', self::$civicrm_bounce_types);
        $params['bounce_reason'] = ($this->reason ? $this->reason : 'Message has been flagged as Spam by the recipient');
      }
      elseif (
        $sparkpost_bounce = CRM_Utils_Array::value($this->bounce_class, self::$sparkpost_bounce_types)) {
        $params['bounce_type_id'] = CRM_Utils_Array::value($sparkpost_bounce[3], self::$civicrm_bounce_types);
        $params['bounce_reason'] = $this->reason;
      }
      if (CRM_Utils_Array::value('bounce_type_id', $params)) {
        CRM_Mailing_Event_BAO_Bounce::create($params);
      }
      else {
        // Sparkpost was not, so let CiviCRM have a go at classifying it
        $params['body'] = $this->raw_reason;
        $result = civicrm_api3('Mailing', 'event_bounce', $params);
      }
    }
  }

  /**
   * @throws \API_Exception
   */
  private function sanity_check() {
    if ( !in_array($this->type, array('bounce', 'spam_complaint', 'policy_rejection'))
      || ($this->campaign_id && ($this->campaign_id != CRM_Sparkpost::getSetting('sparkpost_campaign')))
      || (!$this->recipient || !($civimail_bounce_id = $this->recipient))
    ) {
      throw new API_Exception("Invalid API Call, data not sane");
    }
  }

}
