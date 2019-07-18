<?php
use CRM_Sparkpostbounceapi_ExtensionUtil as E;

/**
 * Sparkpostbounceapi.Bounceevent API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_sparkpostbounceapi_bounceevent_spec(&$spec) {
  $spec['type']['api.required'] = 1;
  $spec['campaign_id']['api.required'] = 0;
  $spec['campaign_id']['api.default'] = NULL;
  $spec['recipient']['api.required'] = 1;
  $spec['raw_reason']['api.required'] = 1;
  $spec['reason']['api.required'] = 0;
  $spec['reason']['api.default'] = NULL;
  $spec['bounce_class']['api.required'] = 0;
  $spec['bounce_class']['api.default'] = NULL;
}

/**
 * Sparkpostbounceapi.Bounceevent API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_sparkpostbounceapi_Bounceevent($params) {

  $sparkpost_bounce_event = new CRM_Sparkpostbounceapi_BounceEvent(
    $params['type'],
    $params['campaign_id'],
    $params['recipient'],
    $params['raw_reason'],
    $params['reason'],
    $params['bounce_class']
  );
  try {
    $sparkpost_bounce_event->process();
  } catch (API_Exception $e) {
    // add html error code
    throw new API_Exception($e->getMessage(), /*errorCode*/ 500);
  }
  return civicrm_api3_create_success(['Bounce successful.'], $params, 'sparkpostbounceapi', 'bounceevent');
}
