<?php
class user {
	public $data = array();
	private $id = 0;
	
	// The constructor to set the variables
	public function __construct ($id) {
		$this->id = $id;
		$this->reload();
	}
	
	// Get all Data
	public function reload() {
		global $CONNECTION;
		$this->data = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT * FROM pb_users WHERE userId = '
		. $this->id . ' LIMIT 1'), MYSQLI_ASSOC);
		return true;
	}
	
	
	
	// Get all currently allowed hardware-action-IDs (If true, only the prefered ones)
	public function getCurrentActions($runByDefault = false) {
		global $CONNECTION;
		
		// If user is inactive:
		if ($this->data['active'] != 1)
			return array();
		
		// Admin has under prefered == false rights for every command
		if ($this->data['canManage'] == 1 && !$runByDefault) {
			// Download all possible actions:
			$returner = array();
			$sql = mysqli_query($CONNECTION, 'SELECT commandId FROM pb_commands');
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
				$returner[] = $row['commandId'];
			}
			return $returner;
		}
		
		// Generate the array of all actions possible now:
		$currentTime = date('G') * 60 + date('i');
		$sql = mysqli_query($CONNECTION, 'SELECT commandId FROM pb_timings WHERE FIND_IN_SET(\''
			. date('D') . '\', days)>0 AND timeslotBegin < ' . $currentTime . ' AND timeslotEnd > '
			. $currentTime . ' AND userId = ' . $this->id
			. ($runByDefault ? ' AND runByDefault = 1' : ''));
		
		$returner = array();
		while ($elem = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			$returner[] = $elem['commandId'];
		}
		return array_unique($returner);
	}
	
	
	
}

?>