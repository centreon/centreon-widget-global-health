<?php
/**
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once "../../require.php";

require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . "www/class/centreonLang.class.php";
require_once $centreon_path . 'www/class/centreonMedia.class.php';
require_once $centreon_path . 'www/class/centreonCriticality.class.php';

CentreonSession::start(1);
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}

$db = new CentreonDB();
$pearDB = $db;
if (CentreonSession::checkSession(session_id(), $db) == 0) {
    exit;
}
$dbb = new CentreonDB("centstorage");

/* Init Objects */
$criticality = new CentreonCriticality($db);
$media = new CentreonMedia($db);

/**
 * Displaying a Smarty Template
 */
$path = $centreon_path . "www/widgets/global-health/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);
$template->assign("session", session_id());
$template->assign("host_label", _("Hosts"));
$template->assign("svc_label", _("Services"));

$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);


$oreon = $_SESSION["centreon"];

/**
 * Initiate Language class
 */
$centreonLang = new CentreonLang($centreon_path, $oreon);
$centreonLang->bindLang();


	/**
	 * Tab status
	 */
	$tabSatusHost 		= array(0 => "UP", 1 => "DOWN", 2 => "UNREACHABLE", 4 => "PENDING");
	$tabSatusService 	= array(0 => "OK", 1 => "WARNING", 2 => "CRITICAL", 3 => "UNKNOWN", 4 => "PENDING");

    $serviceArray = array();
    $hostArray = array();
    
    foreach($tabSatusService as $key=>$statusService){
        $serviceArray[$tabSatusService[$key]]['value'] = 0;
        $serviceArray[$tabSatusService[$key]]['acknowledged'] = 0;
        $serviceArray[$tabSatusService[$key]]['downtime'] = 0;
        $serviceArray[$tabSatusService[$key]]['percent'] = 0;
    }
    
    foreach($tabSatusHost as $key=>$statusHost){
        $hostArray[$tabSatusHost[$key]]['value'] = 0;
        $hostArray[$tabSatusHost[$key]]['acknowledged'] = 0;
        $hostArray[$tabSatusHost[$key]]['downtime'] = 0;
        $hostArray[$tabSatusHost[$key]]['percent'] = 0;
    }

if(isset($preferences['hosts_services']) && $preferences['hosts_services'] == "hosts"){
        
    $hgName = false;
    if(!empty($preferences['hostgroup'])){
        $sql = "select hg.hg_name from hostgroup hg where hg.hg_id = ".$dbb->escape($preferences['hostgroup'])."";
        $DBRESULT = $db->query($sql);
        $row = $DBRESULT->fetchRow();
        $hgName = $row['hg_name'];
    }
    
    
    $innerjoingroup = "";
    $wheregroup = "";
    if($hgName){
        $innerjoingroup =  " INNER JOIN hosts_hostgroups hhg ON  h.host_id = hhg.host_id " .
                           " INNER JOIN hostgroups hg ON hhg.hostgroup_id = hg.hostgroup_id and hg.name = '".$hgName."' ";
    }
    
	/**$obj->DBC->escape($instance)
	 * Get DB informations for creating Flash
	 */
	$rq1 = 	" SELECT count(DISTINCT h.name) cnt, h.state, SUM(h.acknowledged) as acknowledged, SUM(CASE WHEN h.scheduled_downtime_depth >= 1 THEN 1 ELSE 0 END) AS downtime  " .
			" FROM `hosts` h " .
			$innerjoingroup .
			" WHERE h.enabled = 1 " .
			$oreon->user->access->queryBuilder("AND", "h.name", $oreon->user->access->getHostsString("NAME", $dbb)) .
			" AND h.name NOT LIKE '_Module_%' " .
			" GROUP BY h.state " .
			" ORDER BY h.state";
	$DBRESULT = $dbb->query($rq1);
	$data = array();
	$color = array();
	$legend = array();
	$counter = 0;
	while ($ndo = $DBRESULT->fetchRow()){
        $data[$ndo["state"]]['count'] = $ndo["cnt"];
        $data[$ndo["state"]]['acknowledged'] = $ndo["acknowledged"];
        $data[$ndo["state"]]['downtime'] = $ndo["downtime"];
		//$data[] = $ndo["cnt"];
		//$legend[] = $tabSatusHost[$ndo["state"]];
		//$color[] = $oreon->optGen["color_".strtolower($tabSatusHost[$ndo["state"]])];
		$counter += $ndo["cnt"];
	}
	$DBRESULT->free();

	foreach ($data as $key => $value) {
        $hostArray[$tabSatusHost[$key]]['value'] = $value['count'];
		$valuePercent = round($value['count'] / $counter * 100, 2);
	  	$valuePercent = str_replace(",", ".", $valuePercent);
	  	$hostArray[$tabSatusHost[$key]]['percent'] = $valuePercent;
        $hostArray[$tabSatusHost[$key]]['acknowledged'] = $value['acknowledged'];
        $hostArray[$tabSatusHost[$key]]['downtime'] = $value['downtime'];
        //$hostArray[$tabSatusHost[$key]]['color'] = $oreon->optGen["color_".strtolower($tabSatusHost[$key])];
	}
    
    $template->assign("hosts", $hostArray);
    $template->display("global_health_host.ihtml");

}else if(isset($preferences['hosts_services']) && $preferences['hosts_services'] == "services"){

    $sgName = false;
    if(!empty($preferences['servicegroup'])){
        $sql = "select sg.sg_name from servicegroup sg where sg.sg_id = ".$dbb->escape($preferences['servicegroup'])."";
        $DBRESULT = $db->query($sql);
        $row = $DBRESULT->fetchRow();
        $sgName = $row['sg_name'];
    }
    
    $innerjoingroup = "";
    if($sgName){
        $innerjoingroup =  " INNER JOIN services_servicegroups ssg ON ssg.service_id = s.service_id and ssg.host_id = s.host_id " .
                           " INNER JOIN servicegroups sg ON ssg.servicegroup_id = sg.servicegroup_id and sg.name = '".$sgName."' ";
    }
    
	global $is_admin;

	$is_admin =  $oreon->user->admin;
	$grouplistStr = $oreon->user->access->getAccessGroupsString();

	/**
	 * Get DB informations for creating Flash
	 */
	if (!$is_admin) {
		$rq2 = 	" SELECT count(DISTINCT s.state, s.host_id, s.service_id) count, s.state state, " .
				" SUM(s.acknowledged) as acknowledged, SUM(CASE WHEN s.scheduled_downtime_depth >= 1 THEN 1 ELSE 0 END) AS downtime  " .
				" FROM services s " .
				" INNER JOIN centreon_acl acl ON s.host_id  = acl.host_id AND s.service_id = acl.service_id  " .
				" INNER JOIN hosts h ON s.host_id = h.host_id " .
				$innerjoingroup .
				" WHERE h.name NOT LIKE '_Module_%' ".
				" AND h.enabled = 1 " .
				" AND s.enabled = 1 " .
				" AND acl.group_id IN (".$grouplistStr.") ".
				" GROUP BY s.state ORDER by s.state";
	} else {
		$rq2 = 	" SELECT count(DISTINCT s.state, s.host_id, s.service_id) count, s.state state, " .
				" SUM(s.acknowledged) as acknowledged, SUM(CASE WHEN s.scheduled_downtime_depth >= 1 THEN 1 ELSE 0 END) AS downtime  " .
				" FROM services s" .
				" INNER JOIN hosts h ON s.host_id = h.host_id " .
				$innerjoingroup .
				" WHERE h.name NOT LIKE '_Module_%' ".
				" AND h.enabled = 1 ".
				" AND s.enabled = 1 ".
				" GROUP BY s.state ORDER by s.state";
	}
	$DBRESULT = $dbb->query($rq2);

	$svc_stat = array(0=>0, 1=>0, 2=>0, 3=>0, 4=>0);
	$info = array();
	$color = array();
	$legend = array();
	$counter = 0;
	while ($data = $DBRESULT->fetchRow()) {
		$info[$data["state"]]['count'] = $data["count"];
		$info[$data["state"]]['acknowledged'] = $data["acknowledged"];
		$info[$data["state"]]['downtime'] = $data["downtime"];
		$counter += $data["count"];
		$legend[] = $tabSatusService[$data["state"]];
	}
	$DBRESULT->free();

	/**
	 *  create the dataset
	 */
	foreach ($info as $key => $value) {
        $serviceArray[$tabSatusService[$key]]['value'] = $value['count'];
		$valuePercent = round($value['count'] / $counter * 100, 2);
	  	$valuePercent = str_replace(",", ".", $valuePercent);
        $serviceArray[$tabSatusService[$key]]['percent'] = $valuePercent;
        $serviceArray[$tabSatusService[$key]]['acknowledged'] = $value['acknowledged'];
        $serviceArray[$tabSatusService[$key]]['downtime'] = $value['downtime'];
 	}
 
    $template->assign("services", $serviceArray);
    
    /**
	 * Display Templates
	 */
	$template->display("global_health_service.ihtml");
}
