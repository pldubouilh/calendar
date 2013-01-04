<?php
/**
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 * Copyright (c) 2011 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
namespace OCA\Calendar;
class Util{
	/**
	 * @brief calendar language object
	 * @type object
	 */
	private static $l10n = null;

	/**
	 * @brief categories of the user
	 * @type object
	 */
	protected static $categories = null;
	
	/**
	 * @brief creates a default calendar if no one exists
	 * @param $userid  
	 * @returns true/false
	 *
	 * creates a default calendar if no one exists
	 */
	public static function createDefaultCalendar($userid) {
		//get all calendars with read&write permissions
		$allCalendars = \OCA\Calendar::getAllCalendarsByUser($userid, false, true);
		//check if there is already a calendar with read&write permissions
		if( count($allCalendars) !== 0) {
			return true;
		}
		//generate a name for the new calendar
		$calendarname = self::t('%s\'s calendar', $userid);
		//get the name of the default backend
		$defaultbackend = \OCA\Calendar::getDefaultBackend();
		$defaultbackend = 'database';
		//create a new calendar object
		$calendar = \Sabre\VObject\Component::create('VCALENDAR');
		//create a new calendar
		$newcalendar = \OCA\Calendar::createCalendar($defaultbackend, $calendar);
		//check if the calendar was created successfully
		if(!$newcalendar) {
			throw new \Exception('Creating a new calendar failed');
			/*
			//redirect to the read-only version of the calendar
			$newURL = OC_Helper::linkToRoute('calendar_readonly');
			header('Location: ' . $newURL);
			*/
		}
		return true;
	}
	
	/**
	 * @brief generates an array with all the calendars of a user
	 * @param $userid id of the user
	 * @returns array
	 * 
	 * fetchEventSources generates a multidimensional array with information about all the calendars of a user
	 * !Note: It returns disabled calendars as well
	 * 
	 * structure of returned array:
	 * array[
	 * 1 => array[backgroundColor, borderColor, textColor, editable, enabled, md5, class, cache]
	 * 2 => array[...]
	 * ]
	 */
	public static function fetchEventSources($userid) {
		//remove some old session variables
		unset($_SESSION['calendar']['md5map']);
		//get all calendars
		$calendars = \OCA\Calendar::getAllCalendarsByUser($userid);
		//create empty array for the event sources
		$eventSources = array();
		//generate information for each calendar
		foreach($calendars as $calendar) {
			//get the calendar color
			$backgroundColor = (string) $calendar->__get('X-OWNCLOUD-CALENDARCOLOR');
			//generate the calendar hash
			$md5 = md5((string) $calendar->__get('X-OWNCLOUD-CALENADRID'));
			//add some properties to the array
			$eventSources[] = array(
				//displayname of the calendar
				'displayname'		=> (string) $calendar->__get('X-OWNCLOUD-DISPLAYNAME'),
				//the background color for this calendar
				'backgroundColor'	=> (string) $backgroundColor,
				//the border color differs slightly from the text color
				'borderColor'		=> (string) self::generateBorderColor($backgroundColor),
				//the text color is generated by self::generateTextColor
				'textColor'			=> (string) self::generateTextColor($backgroundColor),
				//check if the calendar is editable
				'editable'			=> (boolean) $calendar->__get('X-OWNCLOUD-ISEDITABLE'),
				//check if the calendar is enabled
				'enabled'			=> (boolean) true, //$calendar->__get('ENABLED'),
				//the calendarid is used as an uid for calendars
				'calendarid'		=> (string) $calendar->__get('X-OWNCLOUD-CALENADRID'),
				//the md5 hash of the calendarid
				'md5' 				=> (string) $md5,
				//set a unique class for the events of each calendar
				'className' 			=> (string) 'calendar_' . $md5,
				//enable caching
				'cache'				=> (boolean) true,
			);
			//create a pointer from md5 hash to calendar uri
			$_SESSION['calendar']['md5map'][$md5] = $calendar->__get('X-OWNCLOUD-CALENADRID');
		}
		//return the array
		return $eventSources;
	}
	
	/**
	 * @brief simple wrapper for self::generateTextColor
	 * @param $backgroundColor
	 * @returns string
	 *
	 * returns an appropriate color for the border
	 */
	public static function generateBorderColor($backgroundColor) {
		return self::generateTextColor($backgroundColor);
	}
	
	/**
	 * @brief simple wrapper for OC_L10N
	 * @param $backgroundColor
	 * @returns string
	 *
	 * returns an appropriate color for the text
	 *
	 * This method implements a recommendation by W3C
	 * http://www.w3.org/TR/AERT#color-contrast
	 */
	public static function generateTextColor($backgroundColor) {
		//remove the leading #
		if(substr_count($backgroundColor, '#') == 1) {
			$backgroundColor = substr($backgroundColor,1);
		}
		//get the numeral values for the colors
		$red = hexdec(substr($backgroundColor,0,2));
		$green = hexdec(substr($backgroundColor,2,2));
		$blue = hexdec(substr($backgroundColor,2,2));
		//calculate the color brightness
		$computation = ((($red * 299) + ($green * 587) + ($blue * 114)) / 1000);
		//return an appropriate color for the text
		return ($computation > 130)?'#000000':'#FAFAFA';
	}
	
	/**
	 * @brief simple wrapper for OC_L10N
	 * @param $toBeTranslated
	 * @returns string
	 *
	 * returns a translated string
	 */
	public static function t($toBeTranslated) {
		//check if the calendar's language object was already initialized
		if(is_null(self::$l10n)) {
			//create a new language object
			self::$l10n = new \OC_L10N('calendar');
		}
		//return the translated string
		return (string) self::$l10n->t($toBeTranslated);
	}
	
	/**
	 * @brief compares the lastModified-value of the object with the submitted timestamp
	 * @param $event - event object
	 * @param $lastmodified - (int) timestamp
	 * @returns true/false
	 *
	 * compares the lastModified-value of the object with the submitted timestamp
	 */
	public static function wasObjectModified($object, $lastmodified) {
		$lastModificationOfObject = $object->__get('LAST-MODIFIED');
		if($lastModificationOfObject && $lastmodified != $lastModificationOfObject->getDateTime()->format('U')) {
			\OCP\JSON::error(array('modified'=>true));
			exit;
		}
		return true;
	}
	
	/** * * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                  *
	 * Implementation of some categorie helper methods  *
	 *                                                  *
	 ** * * * * * * * * * * * * * * * * * * * * * * * * */
	
	/**
	 * @brief returns the categories of the vcategories object
	 * @return (array) $categories
	 */
	public static function getCategoryOptions() {
		$categories = self::getVCategories()->categories();
		return $categories;
	}

	/**
	 * @brief returns the default categories of ownCloud
	 * @return (array) $categories
	 */
	public static function getDefaultCategories() {
		return array(
			(string) self::t('Birthday'),
			(string) self::t('Business'),
			(string) self::t('Call'),
			(string) self::t('Clients'),
			(string) self::t('Deliverer'),
			(string) self::t('Holidays'),
			(string) self::t('Ideas'),
			(string) self::t('Journey'),
			(string) self::t('Jubilee'),
			(string) self::t('Meeting'),
			(string) self::t('Other'),
			(string) self::t('Personal'),
			(string) self::t('Projects'),
			(string) self::t('Questions'),
			(string) self::t('Work'),
		);
	}

	/**
	 * @brief returns the vcategories object of the user
	 * @return (object) $vcategories
	 */
	public static function getVCategories() {
		if (is_null(self::$categories)) {
			self::$categories = new \OC_VCategories('calendar', null, self::getDefaultCategories());
			if(\OC_VCategories::isEmpty('event')) {
				self::scanCategories();
			}
			self::$categories = new \OC_VCategories('event',
				null,
				self::getDefaultCategories());
		}
		return self::$categories;
	}

	/**
	 * check VEvent for new categories.
	 * @see OC_VCategories::loadFromVObject
	 */
	public static function loadCategoriesFromVCalendar($id, OC_VObject $calendar) {
		$object = null;
		if (isset($calendar->VEVENT)) {
			$object = $calendar->VEVENT;
		} else
		if (isset($calendar->VTODO)) {
			$object = $calendar->VTODO;
		} else
		if (isset($calendar->VJOURNAL)) {
			$object = $calendar->VJOURNAL;
		}
		if ($object) {
			self::getVCategories()->loadFromVObject($id, $object, true);
		}
	}

	/**
	 * scan events for categories.
	 * @param $events VEVENTs to scan. null to check all events for the current user.
	 */
	public static function scanCategories($events = null) {
		//TODO - fix it
	/*
		if (is_null($events)) {
			$calendars = OC_Calendar_Calendar::allCalendars(\OCP\User::getUser());
			if(count($calendars) > 0) {
				$events = array();
				foreach($calendars as $calendar) {
					if($calendar['userid'] === OCP\User::getUser()) {
						$calendar_events = OC_Calendar_Object::all($calendar['id']);
						$events = $events + $calendar_events;
					}
				}
			}
		}
		if(is_array($events) && count($events) > 0) {
			$vcategories = new OC_VCategories('event');
			$vcategories->delete($vcategories->categories());
			foreach($events as $event) {
				$vobject = OC_VObject::parse($event['calendardata']);
				if(!is_null($vobject)) {
					$object = null;
					if (isset($calendar->VEVENT)) {
						$object = $calendar->VEVENT;
					} else
					if (isset($calendar->VTODO)) {
						$object = $calendar->VTODO;
					} else
					if (isset($calendar->VJOURNAL)) {
						$object = $calendar->VJOURNAL;
					}
					if ($object) {
						$vcategories->loadFromVObject($event['id'], $vobject, true);
					}
				}
			}
		}
		*/
		return false;
	}

	/** * * * * * * * * * * * * * * * * * * * * * * * *
	 *                                                *
	 * Implementation of some sharing helper methods  *
	 *         Might be deprecated very soon          *
	 *                                                *
	 ** * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * @brief Get the permissions for a calendar / an event
	 * @param (int) $id - id of the calendar / event
	 * @param (string) $type - type of the id (calendar/event)
	 * @return (int) $permissions - CRUDS permissions
	 * @see OCP\Share
	 */
	public static function getPermissions($id, $type) {
		 $permissions_all = OCP\PERMISSION_ALL;

		if($type == self::CALENDAR) {
			$calendar = self::getCalendar($id, false, false);
			if($calendar['userid'] == \OCP\User::getUser()) {
				return $permissions_all;
			} else {
				$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $id);
				if ($sharedCalendar) {
					return $sharedCalendar['permissions'];
				}
			}
		}
		elseif($type == self::EVENT) {
			if(OC_Calendar_Object::getowner($id) == \OCP\User::getUser()) {
				return $permissions_all;
			} else {
				$object = OC_Calendar_Object::find($id);
				$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $object['calendarid']);
				$sharedEvent = OCP\Share::getItemSharedWithBySource('event', $id);
				$calendar_permissions = 0;
				$event_permissions = 0;
				if ($sharedCalendar) {
					$calendar_permissions = $sharedCalendar['permissions'];
				}
				if ($sharedEvent) {
					$event_permissions = $sharedEvent['permissions'];
				}
				return max($calendar_permissions, $event_permissions);
			}
		}
		return 0;
	}
	
	public static function generateCalendarObjectByArray($properties){
		$allproperties = array('backend'	=> null,
							   'color' 		=> null,
							   'calendarid'	=> null,
							   'components' => null);
				
	}
}