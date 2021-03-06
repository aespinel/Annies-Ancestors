<?php
/**
* phpGedView Research Assistant Tool - United States Census 1800 File
*
* phpGedView: Genealogy Viewer
* Copyright (C) 2002 to 2009  PGV Development Team.  All rights reserved.
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*
* @package PhpGedView
* @subpackage Research_Assistant
* @version $Id: Census1800.php 6481 2009-11-28 20:17:25Z fisharebest $
* @author Brandon Gagnon
*/

if (!defined('PGV_PHPGEDVIEW')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

require_once PGV_ROOT.'modules/research_assistant/forms/ra_form.php';
require_once PGV_ROOT.'includes/functions/functions_edit.php';

class Census1800 extends ra_form {

	function header($action, $tableAlign, $heading, $showchoose = false) {
		global $pgv_lang;
		$out = "";
		if ($showchoose) {
			//Row Form
			$out = '<form action="module.php" method="post">';
			$out .= '<input type="hidden" name="mod" value="research_assistant" />' .
				'<input type="hidden" name="action" value="printform" />' .
				'<input type="hidden" name="formname" value="Census1800" />' .
				'<input type="hidden" name="taskid" value="'.$_REQUEST['taskid'].'" />';
			if (!isset($_REQUEST['numOfRows'])) $_REQUEST['numOfRows'] = 1;
			$out .= '<table align="center"><tr><td class="descriptionbox">'.$pgv_lang["rows"].'</td><td class="optionbox"><select name="numOfRows">';
			for($i = 1; $i <= 20; $i++){
				$out .= '<option value="'.$i;
				if ($_REQUEST['numOfRows']==$i) $out .= " selected=\"selected\"";
				$out .= '">'.$i;
			}
			$out .= '</select></td></tr><tr><td colspan="2" class="topbottombar"><input type="submit" value="'.$pgv_lang["okay"].'"/></td></tr></table>';
			$out .= '</form>';
		}

		// Split action and use it for hidden inputs
		$action = parse_url($action);
		global $params;
		parse_str(html_entity_decode($action["query"]), $params);

		// Setup for our form to go through the module system
		$out .=  '<form action="' . $action["path"] . '" method="post">';
		$out .= '<input type="hidden" name="numOfRows" value="'.$_REQUEST['numOfRows'].'" />';
		foreach ($params as $key => $value) {
			$out .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
		}
		$out .= '<table id="Census1800" class="list_table" align="' . $tableAlign . '">';
		$out .= '<tr>';
		$out .= '<th colspan="12" align="right"class="topbottombar"><h2>' . $heading . '</h2></th>';
		$out .= '</tr>';
		return $out;
	}

	/**
	* override method from ra_form.php
	*/
	function simpleCitationForm($citation) {
		global $pgv_lang, $factarray;
		if (empty($_POST['data']))
			$data = array();
		if (empty($_REQUEST['row']))
			$row = 1;

		$citation = $this->getSourceCitationData();
		$page = "";
		$callno = "";
		$date = $citation['ts_date'];
		$ct = preg_match("/Page: (.*), .*: (.*)/", $citation['ts_page'], $match);
		if ($ct > 0) {
			$page = trim($match[1]);
			$callno = trim($match[2]);
		}

		$city = "";
		$county = "";
		$state = "";
		if (!empty($citation['ts_array']['city'])) $city = $citation['ts_array']['city'];
		if (!empty($citation['ts_array']['county'])) $county = $citation['ts_array']['county'];
		if (!empty($citation['ts_array']['state'])) $state = $citation['ts_array']['state'];

//        Start of Table
		$out = '<tr>
			<td class="descriptionbox">'.print_help_link("edit_media_help", "qm",'',false,true).$factarray['OBJE'].'</td>
			<td class="optionbox" colspan="5"><input type="hidden" name="OBJE" id="OBJE" value="'.$citation['ts_obje'].'"/>';
			$out .= "<div id=\"censusPicDiv\" style=\"display";
			if(!empty($citation['ts_obje']))
			{
				$out .= ":block\">";
				/*@var $picture Media*/
				$picture = Media::getInstance($citation['ts_obje']);
				if(!is_null($picture))
				{
					$out .= "<span id=\"censusImgSpan\">".$picture->getFullName().'</span><br/><img id="censusImage" src="'.$picture->getThumbnail().'" />';
				}
				else
				{
					$out .= "<span id=\"censusImgSpan\"></span><br /><img id=\"censusImage\" src=\"\" />";
				}
			}
			else
			{
				$out .= ":none\">";
				$out .= "<span id=\"censusImgSpan\"></span><br /><img id=\"censusImage\" src=\"\" />";
			}
			$out .="</div>";
			$out .= print_findmedia_link("OBJE", true, '', true);
			$out .= '<br /><a href="javascript:;" onclick="pastefield=document.getElementById(\'OBJE\'); window.open(\'addmedia.php?action=showmediaform\', \'\', \'top=50,left=50,width=600,height=500,resizable=1,scrollbars=1\'); return false;">'.$pgv_lang["add_media"].'</a>';
			$out .= '</td></tr>';
			$out .= '<tr><td class="descriptionbox">'.$pgv_lang["state"].'</td><td class="optionbox"><input name="state" type="text" size="27"  value="'.htmlentities($state).'"></td>';
			$out .= '<td class="descriptionbox">'.$pgv_lang["call/url"].'</td><td class="optionbox"><input name="CallNumberURL" type="text" size="27" value="'.htmlentities($callno).'"></td>';
			$out .= '<td class="descriptionbox">'.$pgv_lang["enumDate"].'</td><td class="optionbox"><input name="EnumerationDate" type="text" size="27" value="'.htmlentities($date).'"></td></tr>';
			$out .= '<tr><td class="descriptionbox">'.$pgv_lang["county"].'</td><td class="optionbox"><input name="county" type="text" size="27" value="'.htmlentities($county).'"></td>';
			$out .= '<td class="descriptionbox">'.$pgv_lang["city"].'</td><td class="optionbox"><input name="city" type="text" size="27" value="'.htmlentities($city).'"></td>';
			$out .= '<td class="descriptionbox">'.$pgv_lang["page"].'</td><td class="optionbox"><input name="page" type="text" size="5" value="'.htmlentities($page).'"></td></tr>';
			//        Next Table
			$out .= '<tr><td colspan="6"><table align="center" id="inputTable" dir="ltr">';
			$out .= '<td class="descriptionbox" align="center" rowspan="2">Names of heads of families</td>';
			$out .= '<td colspan="5" class="descriptionbox" align="center">Free White Males</td>';
			$out .= '<td colspan="5" class="descriptionbox" align="center">Free White Females</td>';
			$out .= '<td class="descriptionbox" align="center" rowspan="2">All other<br/> free persons</td>';
			$out .= '<td class="descriptionbox" align="center" rowspan="2">Slaves</td>';
			// Next row of description cells
			$out .= '<tr><td class="descriptionbox">Under 10</td><td class="descriptionbox">10 thru 15</td>';
			$out .= '<td class="descriptionbox">16 thru 25</td><td class="descriptionbox">26 thru 44</td><td class="descriptionbox">45 and over</td>';
			$out .= '<td class="descriptionbox">Under 10</td><td class="descriptionbox">10 thru 15</td>';
			$out .= '<td class="descriptionbox">16 thru 25</td><td class="descriptionbox">26 thru 44</td><td class="descriptionbox">45 and over</td></tr>';
			// Country, City, Page, Head of Family input boxes
			if(!isset($_REQUEST['numOfRows'])) $_REQUEST['numOfRows'] = 1;
			for($i = 0; $i < $_REQUEST['numOfRows']; $i++){
					$value = "";
			if (isset($citation['ts_array']['rows'][$i]['headName'])) $value = $citation['ts_array']['rows'][$i]['headName'];
				$out .= '<tr><td class="optionbox"><input name="headName'.$i.'" type="text" size="19" value="'.htmlentities($value).'"></td>';
			//        Free white males input boxes
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['underTenM'])) $value = $citation['ts_array']['rows'][$i]['underTenM'];
			$out .= '<td class="optionbox"><input name="underTenM'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['tenThruFifteenM'])) $value = $citation['ts_array']['rows'][$i]['tenThruFifteenM'];
			$out .= '<td class="optionbox"><input name="tenThruFifteenM'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['sixteenThruTwentyfiveM'])) $value = $citation['ts_array']['rows'][$i]['sixteenThruTwentyfiveM'];
			$out .= '<td class="optionbox"><input name="sixteenThruTwentyfiveM'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['twentysixThruFortyfourM'])) $value = $citation['ts_array']['rows'][$i]['twentysixThruFortyfourM'];
			$out .= '<td class="optionbox"><input name="twentysixThruFortyfourM'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['fortyfiveAndOverM'])) $value = $citation['ts_array']['rows'][$i]['fortyfiveAndOverM'];
			$out .= '<td class="optionbox"><input name="fortyfiveAndOverM'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			// Free white females input boxes
			$value = $row['underTenF'];
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['headName'])) $value = $citation['ts_array']['rows'][$i]['headName'];
			$out .= '<td class="optionbox"><input name="underTenF'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = $row['tenThruFifteenF'];
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['headName'])) $value = $citation['ts_array']['rows'][$i]['headName'];
			$out .= '<td class="optionbox"><input name="tenThruFifteenF'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['sixteenThruTwentyfiveF'])) $value = $citation['ts_array']['rows'][$i]['sixteenThruTwentyfiveF'];
			$out .= '<td class="optionbox"><input name="sixteenThruTwentyfiveF'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['twentysixThruFortyfourF'])) $value = $citation['ts_array']['rows'][$i]['twentysixThruFortyfourF'];
			$out .= '<td class="optionbox"><input name="twentysixThruFortyfourF'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['fortyfiveAndOverF'])) $value = $citation['ts_array']['rows'][$i]['fortyfiveAndOverF'];
			$out .= '<td class="optionbox"><input name="fortyfiveAndOverF'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td>';
			//   Other Persons and Slaves input boxes
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['otherPersons'])) $value = $citation['ts_array']['rows'][$i]['otherPersons'];
			$out .= '<td class="optionbox"><input name="otherPersons'.$i.'" type="text" size="5" value="'.htmlentities($value).'"></td>';
			$value = "";
			if (isset($citation['ts_array']['rows'][$i]['slaves'])) $value = $citation['ts_array']['rows'][$i]['slaves'];
			$out .= '<td class="optionbox"><input name="slaves'.$i.'" type="text" size="4" value="'.htmlentities($value).'"></td></tr>';
		}
		$out .= '</table></td></tr>';
		return $out;
	}

	function footer() {
		return '</table></form>';
	}

	function display_form() {
		$out = $this->header("module.php?mod=research_assistant&form=Census1800&action=func&func=step2&taskid=".$_REQUEST['taskid'], "center", "1800 United States Federal Census", true);
		$out .= $this->sourceCitationForm(5);
		$out .= $this->footer();
		return $out;
	}

	function step2() {
		$this->processSourceCitation();

		$out = $this->header("module.php?mod=research_assistant&form=Census1800&action=func&func=step3&taskid=" . $_REQUEST['taskid'], "center", "1800 United States Federal Census");
		$out .= $this->editFactsForm();
		$out .= $this->footer();

		return $out;
	}

	function step3() {
		global $pgv_lang;

		$out = $this->processFactsForm();

		// Complete the Task.
		ra_functions::completeTask($_REQUEST['taskid'], $_REQUEST['form']);
		// Tell the user their form submitted successfully.
		$out .= ra_functions::print_menu();
		$out .= ra_functions::printMessage($pgv_lang["success"],true);

		// Return it to the buffer.
		return $out;
	}

	/**
	* Override method from ra_form
	*/
	function processSimpleCitation() {
		global $TBLPREFIX;
		//-- delete any old census records
		PGV_DB::prepare("DELETE FROM {$TBLPREFIX}taskfacts WHERE tf_t_id=? AND tf_factrec ".PGV_DB::$LIKE." ?")->execute(array($_REQUEST['taskid'], '1 CENS%'));

		// Set our output to nothing, this supresses a warning that we would otherwise get.
		$out = "";
		$factrec = "1 CENS";
		$factrec .= "\r\n2 DATE ";
		$factrec .=!empty($_POST['EnumerationDate'])?$_POST['EnumerationDate']:"1800";
		$factrec .= "\r\n2 PLAC ".$_POST['city'].", ".$_POST['county'].", ".$_POST['state'].", USA";

		$people = $this->getPeople();
		$pids = array_keys($people);
		//-- store the fact associations in the database
		PGV_DB::prepare("INSERT INTO {$TBLPREFIX}taskfacts (tf_id, tf_t_id, tf_factrec, tf_people, tf_multiple, tf_type) VALUES (?, ?, ?, ?, ?, ?)")->execute(array(get_next_id("taskfacts", "tf_id"), $_REQUEST['taskid'], $factrec, implode(";", $pids), 'Y', 'indi'));

		$rows = array();
		$text = $_POST['city'].", ".$_POST['county'].", ".$_POST['state'].", 1800 US Census";
		for($number = 0; $number < $_REQUEST['numOfRows']; $number++)
		{
			$rows[$number] = array(
			'headName'=>$_POST["headName".$number],
			'underTenM'=>$_POST["underTenM".$number],
			'tenThruFifteenM'=>$_POST["tenThruFifteenM".$number],
			"sixteenThruTwentyfiveM"=>$_POST["sixteenThruTwentyfiveM".$number],
			"twentysixThruFortyfourM"=>$_POST["twentysixThruFortyfourM".$number],
			"fortyfiveAndOverM"=>$_POST["fortyfiveAndOverM".$number],
			"underTenF"=>$_POST["underTenF".$number],
			"tenThruFifteenF"=>$_POST["tenThruFifteenF".$number],
			"sixteenThruTwentyfiveF"=>$_POST["sixteenThruTwentyfiveF".$number],
			"twentysixThruFortyfourF"=>$_POST["twentysixThruFortyfourF".$number],
			"fortyfiveAndOverF"=>$_POST["fortyfiveAndOverF".$number],
			"otherPersons"=>$_POST["otherPersons".$number],
			"slaves"=>$_POST["slaves".$number]
			);
			$text .=$number==0?"" :"\r\n";
			$text .= "\r\nHead of Family: ".$_POST["headName".$number];
			$text .= "\r\nMales under 10: ".$_POST["underTenM".$number];
			$text .= "\r\nMales Ten thru fifteen: ".$_POST["tenThruFifteenM".$number];
			$text .= "\r\nMales Sixteen thru Twenty five: ".$_POST["sixteenThruTwentyfiveM".$number];
			$text .= "\r\nMales Twenty six thru Forty four: ".$_POST["twentysixThruFortyfourM".$number];
			$text .= "\r\nMales Forty five and Over: ".$_POST["fortyfiveAndOverM".$number];
			$text .= "\r\nFemales under 10: ".$_POST["underTenF".$number];
			$text .= "\r\nFemales Ten thru fifteen: ".$_POST["tenThruFifteenF".$number];
			$text .= "\r\nFemales Sixteen thru Twenty five: ".$_POST["sixteenThruTwentyfiveF".$number];
			$text .= "\r\nFemales Twentysix thru Forty four: ".$_POST["twentysixThruFortyfourF".$number];
			$text .= "\r\nFemales Forty five and Over: ".$_POST["fortyfiveAndOverF".$number];
			$text .= "\r\nAll other Free Persons: ".$_POST["otherPersons".$number];
			$text .= "\r\nSlaves: ".$_POST["slaves".$number];
		}

		$citation = array(
			"PAGE"=>"Page: ".$_POST['page'].", Call Number/URL: ".$_POST['CallNumberURL'],
			"QUAY"=>'',
			"DATE"=>!empty($_POST['EnumerationDate'])?$_POST['EnumerationDate']:"1800",
			"TEXT"=>$text,
			"OBJE"=>'',
			"array"=>array(
			'city'=>$_POST['city'],
			'county'=>$_POST['county'],
			'state'=>$_POST['state'],
			'rows'=>$rows));

		return $citation;
	}
}

?>
