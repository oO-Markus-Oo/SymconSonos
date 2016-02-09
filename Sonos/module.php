<?
class Sonos extends IPSModule
{
    
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("IPAddress", "");
        $this->RegisterPropertyInteger("TimeOut", 1000);
        $this->RegisterPropertyInteger("DefaultVolume", 15);
        $this->RegisterPropertyInteger("UpdateGroupingFrequency", 120);
        $this->RegisterPropertyBoolean("GroupForcing", false);
        $this->RegisterPropertyBoolean("MuteControl", false);
        $this->RegisterPropertyBoolean("LoudnessControl", false);
        $this->RegisterPropertyBoolean("BassControl", false);
        $this->RegisterPropertyBoolean("TrebleControl", false);
        $this->RegisterPropertyBoolean("BalanceControl", false);
        $this->RegisterPropertyBoolean("SleeptimerControl", false);
        $this->RegisterPropertyBoolean("PlaylistControl", false);
        $this->RegisterPropertyBoolean("IncludeTunein", "");
        $this->RegisterPropertyString("FavoriteStation", "");
        $this->RegisterPropertyString("WebFrontStations", "");
        $this->RegisterPropertyString("RINCON", "");
       
    }
    
    public function ApplyChanges()
    {
        $ipAddress = $this->ReadPropertyString("IPAddress");
        if ($ipAddress){
            $curl = curl_init();
            curl_setopt_array($curl, array( CURLOPT_RETURNTRANSFER => 1,
                                            CURLOPT_URL => 'http://'.$ipAddress.':1400/xml/device_description.xml' ));

            if(!curl_exec($curl))  die('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
        }

        //Never delete this line!
        parent::ApplyChanges();

        if(!$this->ReadPropertyString("RINCON"))
        {
            $this->UpdateRINCON();
            return true;
        }
                        
        
        // Start create profiles
        $this->RegisterProfileIntegerEx("Status.SONOS", "Information", "", "",   Array( Array(0, "Prev",       "", -1),
                                                                                        Array(1, "Play",       "", -1),
                                                                                        Array(2, "Pause",      "", -1),
                                                                                        Array(3, "Stop",       "", -1),
                                                                                        Array(4, "Next",       "", -1),
                                                                                        Array(5, "Transition", "", -1) ));
        $this->RegisterProfileInteger("Volume.SONOS",   "Intensity",   "", " %",    0, 100, 1);
        $this->RegisterProfileInteger("Tone.SONOS",     "Intensity",   "", " %",  -10,  10, 1);
        $this->RegisterProfileInteger("Balance.SONOS",  "Intensity",   "", " %", -100, 100, 1);
        $this->RegisterProfileIntegerEx("Switch.SONOS", "Information", "",   "", Array( Array(0, "Off", "", 0xFF0000),
                                                                                        Array(1, "On",  "", 0x00FF00) ));
        
        //Build Radio Station Associations according to user settings
        if(!IPS_VariableProfileExists("Radio.SONOS"))
            $this->UpdateRadioStations();

        // Build Group Associations according Sonos Instance settings
        if(!IPS_VariableProfileExists("Groups.SONOS"))
        {
            $allSonosInstances = IPS_GetInstanceListByModuleID("{F6F3A773-F685-4FD2-805E-83FD99407EE8}");
            $GroupAssociations = Array(Array(0, "none", "", -1));

            foreach($allSonosInstances as $key=>$SonosID) {
                if (@GetValueBoolean(IPS_GetVariableIDByName("Coordinator",$SonosID)))
                   $GroupAssociations[] = Array($SonosID, IPS_GetName($SonosID), "", -1);
            }

            $this->RegisterProfileIntegerEx("Groups.SONOS", "Network", "", "", $GroupAssociations);
        }
        // End Create Profiles     
   
        // Start Register variables and Actions
        // 1) general availabe
        $this->RegisterVariableString("nowPlaying", "nowPlaying", "", 20);
        $this->RegisterVariableInteger("Radio", "Radio", "Radio.SONOS", 21);
        $this->RegisterVariableInteger("Status", "Status", "Status.SONOS", 29);
        $this->RegisterVariableInteger("Volume", "Volume", "Volume.SONOS", 30);

        $this->EnableAction("Radio");
        $this->EnableAction("Status");
        $this->EnableAction("Volume");

        // 2) Add/Remove according to feature activation
        // create link list for deletion of liks if target is deleted
        $links = Array();
        foreach( IPS_GetLinkList() as $key=>$LinkID ){
            $links[] =  Array( ('LinkID') => $LinkID, ('TargetID') =>  IPS_GetLink($LinkID)['TargetID'] );
        }
        
          
        // 2a) Bass
        if ($this->ReadPropertyBoolean("BassControl")){
            $this->RegisterVariableInteger("Bass", "Bass", "Tone.SONOS", 36);
            $this->EnableAction("Bass");
        }else{
            $this->removeVariableAction("Bass", $links);
        }

        // 2b) Treble
        if ($this->ReadPropertyBoolean("TrebleControl")){
            $this->RegisterVariableInteger("Treble", "Treble", "Tone.SONOS", 37);
            $this->EnableAction("Treble");
        }else{
            $this->removeVariableAction("Treble", $links);
        }

        // 2c) Mute
        if ($this->ReadPropertyBoolean("MuteControl")){
            $this->RegisterVariableInteger("Mute","Mute", "Switch.SONOS", 31);
            $this->EnableAction("Mute");
        }else{
            $this->removeVariableAction("Mute", $links);
        }

        // 2d) Loudness
        if ($this->ReadPropertyBoolean("LoudnessControl")){
            $this->RegisterVariableInteger("Loudness", "Loudness", "Switch.SONOS", 35);
            $this->EnableAction("Loudness");
        }else{
            $this->removeVariableAction("Loudness", $links);
        }

        // 2e) Balance
        if ($this->ReadPropertyBoolean("BalanceControl")){
            $this->RegisterVariableInteger("Balance", "Balance", "Balance.SONOS", 38);
            $this->EnableAction("Balance");
        }else{
            $this->removeVariableAction("Balance", $links);
        }
        
        // 2f Sleeptimer
        if ($this->ReadPropertyBoolean("SleeptimerControl")){
            $this->RegisterVariableInteger("Sleeptimer", "Sleeptimer", "", 39);
        }else{
            $this->removeVariable("Sleeptimer", $links);
        }
     
        // 2g Playlists
        if ($this->ReadPropertyBoolean("PlaylistControl")){
            if(!IPS_VariableProfileExists("Playlist.SONOS"))
                $this->RegisterProfileIntegerEx("Playlist.SONOS", "Database", "", "", Array());
            $this->RegisterVariableInteger("Playlist", "Playlist", "Playlist.SONOS", 22);
            $this->EnableAction("Playlist");
        }else{
            $this->removeVariable("Playlist", $links);
        }



        // 2h) GroupVolume, GroupMembers, MemberOfGroup
        IPS_SetHidden( $this->RegisterVariableString("GroupMembers", "GroupMembers", "", 10), true);
        IPS_SetHidden( $this->RegisterVariableBoolean("Coordinator", "Coordinator", "", 10), true);
        $this->RegisterVariableInteger("GroupVolume", "GroupVolume", "Volume.SONOS", 11);
        $this->EnableAction("GroupVolume");
        $this->RegisterVariableInteger("MemberOfGroup", "MemberOfGroup", "Groups.SONOS", 12);
        $this->EnableAction("MemberOfGroup");
        
        // End Register variables and Actions
        
        // Start add scripts for regular status and grouping updates
        // 1) _updateStatus 
        $statusScriptID = @$this->GetIDForIdent("_updateStatus");
        if ( $statusScriptID === false ){
          $statusScriptID = $this->RegisterScript("_updateStatus", "_updateStatus", file_get_contents(__DIR__ . "/_updateStatus.php"), 98);
        }else{
          IPS_SetScriptContent($statusScriptID, file_get_contents(__DIR__ . "/_updateStatus.php"));
        }

        IPS_SetHidden($statusScriptID,true);
        IPS_SetScriptTimer($statusScriptID, 5); 

        // 2) _updateGrouping
        $groupingScriptID = @$this->GetIDForIdent("_updateGrouping");
        if ( $groupingScriptID === false ){
          $groupingScriptID = $this->RegisterScript("_updateGrouping", "_updateGrouping", file_get_contents(__DIR__ . "/_updateGrouping.php"), 99);
        }else{
          IPS_SetScriptContent($groupingScriptID, file_get_contents(__DIR__ . "/_updateGrouping.php"));
        }

        IPS_SetHidden($groupingScriptID,true);
        IPS_SetScriptTimer($groupingScriptID, $this->ReadPropertyString("UpdateGroupingFrequency")); 

        // End add scripts for regular status and grouping updates
    }
    
    /**
    * Start of Module functions
    */

    public function ChangeGroupVolume($increment)
    {
        if (!@GetValueBoolean($this->GetIDForIdent("Coordinator"))) die("This function is only allowed for Coordinators");

        $groupMembers        = GetValueString(IPS_GetObjectIDByName("GroupMembers",$this->InstanceID ));
        $groupMembersArray   = Array();
        if($groupMembers)
            $groupMembersArray = array_map("intval", explode(",",$groupMembers));
        $groupMembersArray[] = $this->InstanceID;
            
        foreach($groupMembersArray as $key=>$ID) {
          $newVolume = (GetValueInteger(IPS_GetObjectIDByName("Volume",$ID)) + $increment);
          if ($newVolume > 100){
              $newVolume = 100;
          }elseif($newVolume < 0){
              $newVolume = 0;
          } 
          try{
            SNS_SetVolume($ID, $newVolume );
          }catch (Exception $e){}
        }

        $GroupVolume = 0;
        foreach($groupMembersArray as $key=>$ID) {
          $GroupVolume += GetValueInteger(IPS_GetObjectIDByName("Volume", $ID));
        }

        SetValueInteger(IPS_GetObjectIDByName("GroupVolume", $this->InstanceID), intval(round($GroupVolume / sizeof($groupMembersArray))));
    }

    public function ChangeVolume($increment)
    {
        $newVolume = (GetValueInteger($this->GetIDForIdent("Volume")) + $increment);
        try{
          $this->SetVolume($newVolume);
        }catch (Exception $e){throw $e;}
    }

    public function DeleteSleepTimer()
    {
        $targetInstance = $this->findTarget();

        if($targetInstance === $this->InstanceID){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
               throw new Exception("Sonos Box ".$ip." is not available");

            include_once(__DIR__ . "/sonosAccess.php");
            (new SonosAccess($ip))->SetSleeptimer(0,0,0);
        }else{
            SNS_DeleteSleepTimer($targetInstance);
        }
    }
    
    public function Next()
    {
        $targetInstance = $this->findTarget();

        if($targetInstance === $this->InstanceID){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
                throw new Exception("Sonos Box ".$ip." is not available");

            include_once(__DIR__ . "/sonosAccess.php");
            (new SonosAccess($ip))->Next();
        }else{
            SNS_Next($targetInstance);
        }
    }
    
    public function Pause()
    {
        $targetInstance = $this->findTarget();

        if($targetInstance === $this->InstanceID){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
                throw new Exception("Sonos Box ".$ip." is not available");

            SetValue($this->GetIDForIdent("Status"), 2);
            include_once(__DIR__ . "/sonosAccess.php");
            $sonos = new SonosAccess($ip);
            if($sonos->GetTransportInfo() == 1) $sonos->Pause();
        }else{
            SNS_Pause($targetInstance);
        }
    }

    public function Play()
    {
        $targetInstance = $this->findTarget();

        if($targetInstance === $this->InstanceID){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
                throw new Exception("Sonos Box ".$ip." is not available");

            SetValue($this->GetIDForIdent("Status"), 1);
            include_once(__DIR__ . "/sonosAccess.php");
            (new SonosAccess($ip))->Play();
        }else{
            SNS_Play($targetInstance);
        }
    }

    public function PlayFiles(array $files, $volumeChange)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        include_once(__DIR__ . "/sonosAccess.php");
        $sonos = new SonosAccess($ip);
    
        $positionInfo       = $sonos->GetPositionInfo();
        $mediaInfo          = $sonos->GetMediaInfo();
        $transportInfo      = $sonos->GetTransportInfo();
        $isGroupCoordinator = @GetValueBoolean($this->GetIDForIdent("Coordinator"));
        if($isGroupCoordinator){
          $volume = GetValueInteger($this->GetIDForIdent("GroupVolume")); 
        }else{
          $volume = GetValueInteger($this->GetIDForIdent("Volume")); 
        }

        //adjust volume if needed
        if($volumeChange != 0){
          // pause if playing
          if($transportInfo==1) $sonos->Pause(); 
          
          // volume request absolte or relative?
          if($volumeChange[0] == "+" || $volumeChange[0] == "-"){
            if($isGroupCoordinator){
              $this->changeGroupVolume($volumeChange);
            }else{
              $this->ChangeVolume($volumeChange);
            }
          }else{
            if($isGroupCoordinator){
              $this->SetGroupVolume($volumeChange);
            }else{
              $this->SetVolume($volumeChange); 
            }
          }

        }

        foreach ($files as $key => $file) {
          // only files on SMB share or http server can be used
          if (preg_match('/^\/\/[\w,.,\d,-]*\/\S*/',$file) == 1){
            $uri = "x-file-cifs:".$file;
          }elseif (preg_match('/^http:\/\/[\w,.,\d,-,:]*\/\S*/',$file) == 1){
            $uri = $file;
          }else{
            throw new Exception("File (".$file.") has to be located on a Samba share (e.g. //ipsymcon.fritz.box/tts/text.mp3) or a HTTP server (e.g. http://ipsymcon.fritz.box/tts/text.mp3)");
          }

          $sonos->SetAVTransportURI($uri);
          $sonos->Play();
          IPS_Sleep(500);
          $fileTransportInfo = $sonos->GetTransportInfo();
          while ($fileTransportInfo==1 || $fileTransportInfo==5){ 
            IPS_Sleep(200);
            $fileTransportInfo = $sonos->GetTransportInfo();
          }
        }

        // reset to what was playing before
        $sonos->SetAVTransportURI($mediaInfo["CurrentURI"],$mediaInfo["CurrentURIMetaData"]);
        if($positionInfo["Track"] > 1 )
          $sonos->Seek("TRACK_NR",$positionInfo["Track"]);
        if($positionInfo["TrackDuration"] != "0:00:00" )
          $sonos->Seek("REL_TIME",$positionInfo["RelTime"]);
 

        if($volumeChange != 0){
          // set back volume
          if($isGroupCoordinator){
            $this->SetGroupVolume($volume);
          }else{
            $this->SetVolume($volume); 
          }
        }

        if ($transportInfo==1){
          $sonos->Play();
        }
    }

    public function Previous()
    {
        $targetInstance = $this->findTarget();

        if($targetInstance === $this->InstanceID){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
                throw new Exception("Sonos Box ".$ip." is not available");

            include_once(__DIR__ . "/sonosAccess.php");
            (new SonosAccess($ip))->Previous();
        }else{
            SNS_Previous($targetInstance);
        }
    }
    
    public function SetAnalogInput($input_instance)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        if(@GetValue($this->GetIDForIdent("MemberOfGroup")))
          $this->SetGroup(0);

        include_once(__DIR__ . "/sonosAccess.php");
        $sonos = new SonosAccess($ip);
        
        $sonos->SetAVTransportURI("x-rincon-stream:".IPS_GetProperty($input_instance ,"RINCON"));
        $sonos->Play();
    }

    public function SetBalance($balance)	
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        $leftVolume  = 100;
        $rightVolume = 100;     
        if ( $balance < 0 ){
          $rightVolume = 100 + $balance;
        }else{
          $leftVolume  = 100 - $balance;
        }

        include_once(__DIR__ . "/sonosAccess.php");
        $sonos = (new SonosAccess($ip));
        $sonos->SetVolume($leftVolume,'LF');
        $sonos->SetVolume($rightVolume,'RF');
        if (!$this->ReadPropertyBoolean("BalanceControl")) SetValue($this->GetIDForIdent("Balance"), $balance);
    }
    
    public function SetBass($bass)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        include_once(__DIR__ . "/sonosAccess.php");
        (new SonosAccess($ip))->SetBass($bass);
        if (!$this->ReadPropertyBoolean("BassControl")) SetValue($this->GetIDForIdent("Bass"), $bass);
    }

    public function SetDefaultGroupVolume()
    {
        if (!@GetValueBoolean($this->GetIDForIdent("Coordinator"))) die("This function is only allowed for Coordinators");

        $groupMembers        = GetValueString(IPS_GetObjectIDByName("GroupMembers",$this->InstanceID ));
        $groupMembersArray   = Array();
        if($groupMembers)
            $groupMembersArray = array_map("intval", explode(",",$groupMembers));
        $groupMembersArray[] = $this->InstanceID;

        foreach($groupMembersArray as $key=>$ID) {
          try{
            SNS_SetDefaultVolume($ID);
          }catch (Exception $e) {}
        }
        
        $GroupVolume = 0;
        foreach($groupMembersArray as $key=>$ID) {
          $GroupVolume += GetValueInteger(IPS_GetObjectIDByName("Volume", $ID));
        }

        SetValueInteger(IPS_GetObjectIDByName("GroupVolume", $this->InstanceID), intval(round($GroupVolume / sizeof($groupMembersArray))));
    }

    public function SetDefaultVolume()
    {
        try{
          $this->SetVolume($this->ReadPropertyInteger("DefaultVolume"));
        }catch(Exception $e){throw $e;}
    }
    
    public function SetGroup($groupCoordinator)
    {
        // Instance has Memners, do nothing
        if(@GetValueString($this->GetIDForIdent("GroupMembers"))) return;
        // Do not try to assign to itself
        if($this->InstanceID === $groupCoordinator) $groupCoordinator = 0;

        $startGroupCoordinator = GetValue($this->GetIDForIdent("MemberOfGroup"));

        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        // cleanup old group
        if($startGroupCoordinator){
            $groupMembersID = @IPS_GetObjectIDByIdent("GroupMembers",$startGroupCoordinator);
            $currentMembers = explode(",",GetValueString($groupMembersID));
            $currentMembers = array_filter($currentMembers, function($v) { return $v != ""; });
            $currentMembers = array_filter($currentMembers, function($v) { return $v != $this->InstanceID ; });
            SetValueString($groupMembersID,implode(",",$currentMembers));
            if(!count($currentMembers)){
                IPS_SetHidden(IPS_GetVariableIDByName("GroupVolume",$startGroupCoordinator),true);
                IPS_SetHidden(IPS_GetVariableIDByName("MemberOfGroup",$startGroupCoordinator),false);
            }
        }

        // get variable of coordinator members to be updated
        $currentMembers = Array();
        if($groupCoordinator){
            $groupMembersID = @IPS_GetObjectIDByIdent("GroupMembers",$groupCoordinator);
            $currentMembers = explode(",",GetValueString($groupMembersID));
            $currentMembers = array_filter($currentMembers, function($v) { return $v != ""; });
            $currentMembers = array_filter($currentMembers, function($v) { return $v != $this->InstanceID ; });
            if($groupCoordinator)
                $currentMembers[] = $this->InstanceID;

            SetValueString($groupMembersID,implode(",",$currentMembers));
            $uri            = "x-rincon:".IPS_GetProperty($groupCoordinator ,"RINCON");
            SetValueBoolean($this->GetIDForIdent("Coordinator"),false);
            @IPS_SetVariableProfileAssociation("Groups.SONOS", $this->InstanceID, "", "", -1);
        }else{
            $uri            = "";
            SetValueBoolean($this->GetIDForIdent("Coordinator"),true);
            @IPS_SetVariableProfileAssociation("Groups.SONOS", $this->InstanceID, IPS_GetName($this->InstanceID), "", -1);
        }
        
        // update coordinator members
        SetValue($this->GetIDForIdent("MemberOfGroup"), $groupCoordinator);
  

       
        
        
        // Set relevant variables to hidden/unhidden
        if ($groupCoordinator){
            $hidden = true;
            IPS_SetHidden(IPS_GetVariableIDByName("GroupVolume",$groupCoordinator),false);
            IPS_SetHidden(IPS_GetVariableIDByName("MemberOfGroup",$groupCoordinator),true);
        }else{
            $hidden = false;
        }
        @IPS_SetHidden($this->GetIDForIdent("nowPlaying"),$hidden);
        @IPS_SetHidden($this->GetIDForIdent("Radio"),$hidden);
        @IPS_SetHidden($this->GetIDForIdent("Playlist"),$hidden);
        @IPS_SetHidden($this->GetIDForIdent("Status"),$hidden);
        @IPS_SetHidden($this->GetIDForIdent("Sleeptimer"),$hidden);
        // always hide GroupVolume
        @IPS_SetHidden(IPS_GetVariableIDByName("GroupVolume",$this->InstanceID),true);
        @IPS_SetHidden(IPS_GetVariableIDByName("MemberOfGroup",$this->InstanceID),false);

        include_once(__DIR__ . "/sonosAccess.php");
        (new SonosAccess($ip))->SetAVTransportURI($uri);
    }

    public function SetGroupVolume($volume)
    {
        if (!@GetValueBoolean($this->GetIDForIdent("Coordinator"))) die("This function is only allowed for Coordinators");

        $this->ChangeGroupVolume($volume - GetValue($this->GetIDForIdent("GroupVolume")));
    }

    public function SetLoudness($loudness)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");
 
        include_once(__DIR__ . "/sonosAccess.php");
        (new SonosAccess($ip))->SetLoudness($loudness);
        if ($this->ReadPropertyBoolean("LoudnessControl")) SetValue($this->GetIDForIdent("Loudness"), $loudness);
    }

    public function SetMute($mute)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        include_once(__DIR__ . "/sonosAccess.php");
        (new SonosAccess($ip))->SetMute($mute);
        if ($this->ReadPropertyBoolean("MuteControl")) SetValue($this->GetIDForIdent("Mute"), $mute);
    }
    
    public function SetPlaylist($name){
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        if(@GetValue($this->GetIDForIdent("MemberOfGroup")))
          $this->SetGroup(0);

        include_once(__DIR__ . "/sonosAccess.php");
        $sonos = new SonosAccess($ip);

        $uri = '';
        foreach ((new SimpleXMLElement($sonos->BrowseContentDirectory('SQ:')['Result']))->container as $container) {
            if ($container->xpath('dc:title')[0] == $name){
              $uri = (string)$container->res;
              break;
            }
        }  

        if($uri === '')
            throw new Exception('Playlist \''.$name.'\' not found');

        $sonos->ClearQueue();
        $sonos->AddToQueue($uri);
        $sonos->SetAVTransportURI('x-rincon-queue:'.$this->ReadPropertyString("RINCON").'#0');
        $sonos->Play();

    }

    public function SetRadioFavorite()
    {
        $this->SetRadio($this->ReadPropertyString("FavoriteStation"));
    }
    
    public function SetRadio($radio)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        if(@GetValue($this->GetIDForIdent("MemberOfGroup")))
          $this->SetGroup(0);

        include_once(__DIR__ . "/sonosAccess.php");
        include_once(__DIR__ . "/radio_stations.php");
        $sonos = new SonosAccess($ip);

        // try to find Radio Station URL
        $uri = get_station_url($radio);

        if( $uri == ""){
            // check in TuneIn Favorites
            foreach ((new SimpleXMLElement($sonos->BrowseContentDirectory('R:0/0')['Result']))->item as $item) {
                if ($item->xpath('dc:title')[0] == $radio){
                  $uri = (string)$item->res;
                  break;
                }
            }
        }
  
        if( $uri == "")
         throw new Exception("Radio station " . $radio . " is unknown" ); 

        $sonos->SetRadio($uri, $radio);
        $sonos->Play();
    }
    
    public function SetSleepTimer($minutes)
    {
        $targetInstance = $this->findTarget();

        if($targetInstance === $this->InstanceID){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
                throw new Exception("Sonos Box ".$ip." is not available");

            $hours = 0;

            while( $minutes > 59 ){
                $hours   = $hours + 1;
                $minutes = $minutes - 60;
            }

            include_once(__DIR__ . "/sonosAccess.php");
            (new SonosAccess($ip))->SetSleeptimer($hours,$minutes,0);
        }else{
            SNS_SetSleepTimer($targetInstance,$minutes);
        }
    }

    public function SetSpdifInput($input_instance)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        if(@GetValue($this->GetIDForIdent("MemberOfGroup")))
          $this->SetGroup(0);

        include_once(__DIR__ . "/sonosAccess.php");
        $sonos = new SonosAccess($ip);
        
        $sonos->SetAVTransportURI("x-sonos-htastream:".IPS_GetProperty($input_instance ,"RINCON").":spdif");
        $sonos->Play();
    }

    public function SetTreble($treble)	
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        include_once(__DIR__ . "/sonosAccess.php");
        (new SonosAccess($ip))->SetTreble($treble);
        if (!$this->ReadPropertyBoolean("TrebleControl")) SetValue($this->GetIDForIdent("Treble"), $treble);
    }
    
    public function SetVolume($volume)
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        SetValue($this->GetIDForIdent("Volume"), $volume);
        include_once(__DIR__ . "/sonosAccess.php");
        (new SonosAccess($ip))->SetVolume($volume);
    }

    public function Stop()
    {
        $targetInstance = $this->findTarget();

        if($targetInstance === $this->InstanceID){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
                throw new Exception("Sonos Box ".$ip." is not available");

            SetValue($this->GetIDForIdent("Status"), 3);
            include_once(__DIR__ . "/sonosAccess.php");
            $sonos = new SonosAccess($ip);
            if($sonos->GetTransportInfo() == 1) $sonos->Stop();
        }else{
            SNS_Stop($targetInstance);
        }
    }

    public function UpdatePlaylists()
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        include_once(__DIR__ . "/sonosAccess.php");
        $sonos = new SonosAccess($ip);

        $Associations          = Array();
        $Value                 = 1;

        foreach ((new SimpleXMLElement($sonos->BrowseContentDirectory('SQ:')['Result']))->container as $container) {
            $Associations[] = Array($Value++, (string)$container->xpath('dc:title')[0], "", -1);
            // associations only support up to 32 variables
            if( $Value === 33 ) break;
        }

        if(IPS_VariableProfileExists("Playlist.SONOS"))
            IPS_DeleteVariableProfile("Playlist.SONOS");

        $this->RegisterProfileIntegerEx("Playlist.SONOS", "Database", "", "", $Associations);
      
    }

    public function UpdateRadioStations()
    {
        include_once(__DIR__ . "/radio_stations.php");
        $Associations          = Array();
        $AvailableStations     = get_available_stations();
        $WebFrontStations      = $this->ReadPropertyString("WebFrontStations");
        $WebFrontStationsArray = array_map("trim", explode(",", $WebFrontStations));
        $FavoriteStation       = $this->ReadPropertyString("FavoriteStation");
        $Value                 = 1;

        foreach ( $AvailableStations as $key => $val ) {
            if (in_array( $val['name'], $WebFrontStationsArray) || $WebFrontStations === "<alle>" || $WebFrontStations === "<all>" ) {
                if  ( $val['name'] === $FavoriteStation ){
                    $Color = 0xFCEC00;
                } else {
                    $Color = -1;
                }
                $Associations[] = Array($Value++, $val['name'], "", $Color);
                // associations only support up to 32 variables
                if( $Value === 33 ) break;
            }
        }
       
        if ($this->ReadPropertyString("IncludeTunein") && $Value < 33){
            $ip      = $this->ReadPropertyString("IPAddress");
            $timeout = $this->ReadPropertyString("TimeOut");
            if ($timeout && Sys_Ping($ip, $timeout) != true)
               throw new Exception("Sonos Box ".$ip." is not available");

            include_once(__DIR__ . "/sonosAccess.php");
            $sonos = new SonosAccess($ip);

            foreach ((new SimpleXMLElement($sonos->BrowseContentDirectory('R:0/0')['Result']))->item as $item) {
                $Associations[] = Array($Value++, (string)$item->xpath('dc:title')[0], "", 0x539DE1);
                // associations only support up to 32 variables
                if( $Value === 33 ) break;
            }
        }

        usort($Associations, function($a,$b){return strnatcmp($a[1], $b[1]);});

        $Value = 1;
        foreach($Associations as $Association) {
            $Associations[$Value-1][0] = $Value++ ;
        }

        if(IPS_VariableProfileExists("Radio.SONOS"))
            IPS_DeleteVariableProfile("Radio.SONOS");

        $this->RegisterProfileIntegerEx("Radio.SONOS", "Speaker", "", "", $Associations);
    
    }
 
    public function UpdateRINCON()
    {
        $ip      = $this->ReadPropertyString("IPAddress");
        $timeout = $this->ReadPropertyString("TimeOut");
        if ($timeout && Sys_Ping($ip, $timeout) != true)
           throw new Exception("Sonos Box ".$ip." is not available");

        $curl = curl_init();
        curl_setopt_array($curl, array( CURLOPT_RETURNTRANSFER => 1,
                                        CURLOPT_URL => "http://".$ip.":1400/xml/device_description.xml" ));

        $result = curl_exec($curl);

        if(!$result)
           throw new Exception("Device description could not be read from ".$ip);

        $xmlr = new SimpleXMLElement($result);
        $rincon = str_replace ( "uuid:" , "" , $xmlr->device->UDN );
        if($rincon){
            IPS_SetProperty($this->InstanceID, "RINCON", $rincon );
            IPS_ApplyChanges($this->InstanceID);
        }else{
            throw new Exception("RINCON could not be read from ".$ip);
        }
    }

    /**
    * End of Module functions
    */

    public function RequestAction($Ident, $Value)
    {
        switch($Ident) {
            case "Balance":
                $this->SetBalance($Value);
                break;
            case "Bass":
                $this->SetBass($Value);
                break;
            case "GroupVolume":
                $this->SetGroupVolume($Value);
                break;
            case "Loudness":
                $this->SetLoudness($Value);
                break;
            case "MemberOfGroup":
                $this->SetGroup($Value);
                break;
            case "Mute":
                $this->SetMute($Value);
                break;
            case "Playlist":
                $this->SetPlaylist(IPS_GetVariableProfile("Playlist.SONOS")['Associations'][$Value-1]['Name']);
                break;
            case "Radio":
                $this->SetRadio(IPS_GetVariableProfile("Radio.SONOS")['Associations'][$Value-1]['Name']);
                SetValue($this->GetIDForIdent($Ident), $Value);
                break;
            case "Status":
                switch($Value) {
                    case 0: //Prev
                        $this->Previous();
                        break;
                    case 1: //Play
                        $this->Play();
                        break;
                    case 2: //Pause
                        $this->Pause();
                        break;
                    case 3: //Stop
                        $this->Stop();
                        break;
                    case 4: //Next
                        $this->Next();
                        break;
                }
                break;
            case "Treble":
                $this->SetTreble($Value);
                break;
            case "Volume":
                $this->SetVolume($Value);
                break;
            default:
                throw new Exception("Invalid ident");
        }
    }
    
    protected function findTarget(){
        // instance is a coordinator and can execute command
        if(GetValueBoolean($this->GetIDForIdent("Coordinator")) === true)
            return $this->InstanceID;

        $memberOfGroup = GetValueInteger($this->GetIDForIdent("MemberOfGroup"));
        if($memberOfGroup)
            return $memberOfGroup;
        die("Instance is not a coordinator and group coordinator could not be determined");
    }

    protected function removeVariable($name, $links){
        $vid = @$this->GetIDForIdent($name);
        if ($vid){
            // delete links to Variable
            foreach( $links as $key=>$value ){
                if ( $value['TargetID'] === $vid )
                     IPS_DeleteLink($value['LinkID']);
            }
            $this->UnregisterVariable($name);
        }
    }

    protected function removeVariableAction($name, $links){
        $vid = @$this->GetIDForIdent($name);
        if ($vid){
            // delete links to Variable
            foreach( $links as $key=>$value ){
                if ( $value['TargetID'] === $vid )
                     IPS_DeleteLink($value['LinkID']);
            }
            $this->DisableAction($name);
            $this->UnregisterVariable($name);
        }
    }

    //Remove on next Symcon update
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize) {
        
        if(!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, 1);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if($profile['ProfileType'] != 1)
            throw new Exception("Variable profile type does not match for profile ".$Name);
        }
        
        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
        
    }
    
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations) {
        if ( sizeof($Associations) === 0 ){
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[sizeof($Associations)-1][0];
        }
        
        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        
        foreach($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
        
    }
}
?>
