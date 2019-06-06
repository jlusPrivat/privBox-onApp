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
		$this->data = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT * FROM pb_users WHERE id = '
		. $this->id . ' LIMIT 1'), MYSQLI_ASSOC);
		return true;
	}
	
	
	
	// Get all currently allowed hardware-action-IDs (If true, only the prefered ones)
	public function getCurrentActions($prefered = false) {
		global $CONNECTION;
		
		// Admin has under prefered == false rights for every command
		if ($this->data["admin"] == 1 && !$prefered) {
			// Download all possible actions:
			$returner = array();
			$sql = mysqli_query($CONNECTION, 'SELECT id FROM pb_commands');
			while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
				$returner[] = $row["id"];
			}
			return $returner;
		}
		
		// Also check for active
		if ($this->data["active"] != 1)
			return false;
		
		// Dont forget to check against per_count and quantif
		if ($this->data["per_count"] <= $this->getCurrentCount())
			return false;
		
		// Order by prefered (Prefered has higher priority, so has to be evaluated later)
		$time = (date('G') * 60) + date('i');
		$sql = mysqli_query($CONNECTION, 'SELECT command FROM pb_permissions WHERE user = ' . $this->data["id"] . ' AND from_time < '
		. $time . ' AND to_time > ' . $time . ' AND FIND_IN_SET(\'' . date('N') . '\', days) > 0'
		. ($prefered ? ' AND prefered = 1' : ' ORDER BY prefered'));
		
		// Eval fast the returner
		$returner = array();
		while ($row = mysqli_fetch_array($sql, MYSQLI_ASSOC)) {
			$returner[] = $row["command"];
		}
		return $returner;
		
	}
	
	
	
	// This function returns the number of (successfull) logactivities within the scope of per_quantif
	public function getCurrentCount() {
		global $CONNECTION;
		
		switch($this->data["per_quantif"]) {
			case 0:
				return -1; // Infinitively allowed, so basically its always below the limit
				break;
			case 1:
				$quantif = 3600; // Hour
				break;
			case 2:
				$quantif = 86400; // Day
				break;
			case 3:
				$quantif = 604800; // Week
				break;
			case 4:
				$quantif = 2419200; // Month
				break;
			default:
				return -1;
				break;
		}
		
		$sql = mysqli_fetch_array(mysqli_query($CONNECTION, 'SELECT COUNT(*) as zaehler FROM pb_log WHERE user = '
		. $this->data["id"] . ' AND success = 1 AND typ = 2 AND stamp > ' . (time() - $quantif)), MYSQLI_ASSOC);
		return $sql["zaehler"];
		
	}
	
}

?>