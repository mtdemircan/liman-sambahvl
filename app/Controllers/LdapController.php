<?php
namespace App\Controllers;

class LdapController
{
    private $basedn = "";
	function connect(){
        $ip = $this->getIP();
        $domainname= strtolower(extensionDb('domainName'));
        $user = "administrator@".$domainname;
        $pass = extensionDb('domainPassword');
        $server = 'ldaps://'.$ip;
        $port="636";
        
        $str = explode(".",$domainname);
        $tmp = "";
        for($i=0 ; $i<count($str) ; $i++){
            if($str[$i] == end($str)){
                $tmp .= "DC=".$str[$i];
            }
            else{
                $tmp .= "DC=".$str[$i].",";
            }
        }
        $this->basedn = $tmp;

        $ldap = ldap_connect($server);
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
        $bind=ldap_bind($ldap, $user, $pass);
        if (!$bind) {
            exit('Binding failed');
        }
        return $ldap;
    }

    function close($ldap){
        ldap_close($ldap);
    }

    function getIP(){
        $command = "hostname -I | awk '{print $1}'";
        $ip = runCommand(sudo().$command);
        return $ip;
    }

    function listUsers(){
        $ldap = $this->connect();

        $filter = "objectClass=user";
        $result = ldap_search($ldap, $this->basedn, $filter);
        $entries = ldap_get_entries($ldap,$result);

        $count = ldap_count_entries($ldap, $result);
        $data = [];
        for($i=0 ; $i<$count ; $i++){
            $nameItem = $entries[$i]["name"][0];
            $data[] = [
                "name" => $nameItem
            ];
        }
        $this->close($ldap);

        return view('table', [
            "value" => $data,
            "title" => ["Kullanıcılar"],
            "display" => ["name"]
        ]);

    }
    function listGroups(){
        $ldap = $this->connect();
        $groupType = request("groupType");
        if($groupType == "none")
            $filter="objectClass=group";
        else if($groupType == "security")
            $filter = "(&(objectCategory=group)(groupType:1.2.840.113556.1.4.803:=2147483648))";
        else if($groupType == "distribution")
            $filter = "(&(objectCategory=group)(!(groupType:1.2.840.113556.1.4.803:=2147483648)))";
        
        $result = ldap_search($ldap, $this->basedn, $filter);
        $entries = ldap_get_entries($ldap,$result);
    
        $count = ldap_count_entries($ldap, $result);
        $data = [];
        for($i=0 ; $i<$count ; $i++){
            $nameItem = $entries[$i]["name"][0];
            $data[] = [
                "name" => $nameItem
            ];
        }
        $this->close($ldap);
    
        return view('table', [
            "value" => $data,
            "title" => ["Gruplar"],
            "display" => ["name"]
        ]);
    
    }
    function listComputers(){
        $ldap = $this->connect();
    
        $filter = "objectClass=computer";
        $result = ldap_search($ldap, $this->basedn, $filter);
        $entries = ldap_get_entries($ldap,$result);
    
        $count = ldap_count_entries($ldap, $result);
        $data = [];
        for($i=0 ; $i<$count ; $i++){
            $nameItem = $entries[$i]["name"][0];
            $data[] = [
                "name" => $nameItem
            ];
        }
        $this->close($ldap);
    
        return view('table', [
            "value" => $data,
            "title" => ["Bilgisayarlar"],
            "display" => ["name"]
        ]);
    
    }

    //Site
    function listSites(){

        $ldap = $this->connect();

        $filter = "objectClass=site";
        $result = ldap_search($ldap, "CN=Configuration,".$this->basedn, $filter);
        $entries = ldap_get_entries($ldap,$result);

        $count = ldap_count_entries($ldap, $result);
        $data = [];
        for($i=0 ; $i<$count ; $i++){
            $nameItem = $entries[$i]["name"][0];
            $data[] = [
                "name" => $nameItem,
            ];
        }
        $this->close($ldap);

        return view('table', [
            "value" => $data,
            "title" => ["Sites"],
            "display" => ["name"],
            "menu" => [
                "Site Sil" => [
                    "target" => "deleteSite",
                    "icon" => "fas fa-trash-alt",
                ],  
                "Sunucular" => [
                    "target" => "showServersOfSite",
                    "icon" => "fas fa-server",
                ],  
                "Sunucu Ekle" => [
                    "target" => "addServerToSite",
                    "icon" => "fas fa-plus",
                ],  
            ],
        ]);
    }

    function createSite(){

        validate([
			'newSiteName' => 'required|string',
		]);

        $newSiteName = request("newSiteName");
        $command = "samba-tool sites create ".$newSiteName;
        $commandOutput = runCommand(sudo().$command);
        return respond($commandOutput, 200);
    }

    function deleteSite(){

        $siteName = request("siteName");
        $command = "samba-tool sites remove ".$siteName;
        $commandOutput = runCommand(sudo().$command);
        return respond($commandOutput, 200);
    }

    function serversOfSite(){

        $siteName = request("siteName");

        $ldap = $this->connect();
        $filter = "objectClass=server";

        $result = ldap_search($ldap, "CN=Configuration,".$this->basedn, $filter);
        $entries = ldap_get_entries($ldap,$result);
        $count = ldap_count_entries($ldap, $result);
        $data = [];
        for($i=0 ; $i<$count ; $i++){
            if(str_contains($entries[$i]["distinguishedname"][0], $siteName)){
                $nameItem = $entries[$i]["name"][0];
                $data[] = [
                    "name" => $nameItem,
                ];
            }       
        }
        $this->close($ldap);

        return view('table', [
            "value" => $data,
            "title" => ["Sunucular"],
            "display" => ["name"],
        ]);
    }

    function addServerToSite(){

        $newSiteName = request("newSiteName");
        $ldap = $this->connect();
        $filter = "objectClass=server";
        $result = ldap_search($ldap, "CN=Configuration,".$this->basedn, $filter);
        $entries = ldap_get_entries($ldap,$result);
        $count = ldap_count_entries($ldap, $result);
        $data = [];
        for($i=0 ; $i<$count ; $i++){
            if(!(str_contains($entries[$i]["distinguishedname"][0], $newSiteName))){
                $nameItem = $entries[$i]["name"][0];
                $dnOfServer = $entries[$i]["dn"];
                $data[] = [
                    "name" => $nameItem,
                    "dnOfServer" => $dnOfServer,
                    "newSiteName" => $newSiteName
                ];
            }       
        }
        $this->close($ldap);

        return view('table', [
            "value" => $data,
            "title" => ["Mevcut Sunucular", "*hidden*", "*hidden*"],
            "display" => ["name", "dnOfServer:dnOfServer", "newSiteName:newSiteName"],
            "onclick" => "addThisServer"
        ]);
    }

    function addThisServer(){

        $dnOfServer = request("dnOfServer");
        $newSiteName = request("newSiteName");
        $ldap = $this->connect();

        $newRDN = substr($dnOfServer, 0, strpos($dnOfServer, ","));
        $newParent = "CN=Servers,CN=".$newSiteName.",CN=Sites,CN=Configuration,".$this->basedn;

        if(ldap_rename($ldap, $dnOfServer, $newRDN, $newParent, true)){
            $this->close($ldap);
            return respond("Success!",200);
        }
        else {
            $this->close($ldap);
            return respond("Error!",201);
        }
    }

    function ldapLogin(){

        validate([
			'ip' => 'required|string',
			'username' => 'required|string',
			'password' => 'required|string'

		]);

        $ip = request("ip");
        $username = request("username");
        $pass = request("password");
        $domainname= strtolower($this->getDomainNameAnonymously($ip));
        $user ="administrator@" . $domainname;
        $server = 'ldaps://'.$ip;
        $port="636";
        
        $str = explode(".",$domainname);
        $basedn = "DC=".$str[0].",DC=".$str[1];

        $ldap = ldap_connect($server);
        ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
        $bind=ldap_bind($ldap, $user, $pass);
        if (!$bind) {
            exit('Binding failed');
        }

        $filter = "objectClass=site";
        $result = ldap_search($ldap, "CN=Configuration,".$basedn, $filter);
        $entries = ldap_get_entries($ldap,$result);
        $count = ldap_count_entries($ldap, $result);

        for($i=0 ; $i<$count ; $i++){
            $nameItem[] = $entries[$i]["name"][0];
        }

        $this->close($ldap);
        
        return respond($nameItem,200);
    }

    function getDomainNameAnonymously($server){
        $ldapconn = ldap_connect($server);
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);

        $filter = "objectClass=*";
        if ($ldapconn) {
            // binding anonymously
            $ldapbind = ldap_bind($ldapconn);

            if ($ldapbind) {
                $result = ldap_read($ldapconn, '', '(objectclass=*)', array('namingContexts'));
                $data = ldap_get_entries($ldapconn, $result);

                $baseDN=$data[0]['namingcontexts'][0];
                $base=explode(",",$baseDN);
                $domainName = "";
                for($i = 0; $i < count($base); $i++){
                    if($base[$i] == end($base)){
                        $domainName .= str_replace("DC=","",$base[$i]);
                    }else{
                        $domainName .= str_replace("DC=","",$base[$i]) . ".";
                    }
                }
                ldap_close($ldapconn);

            } else {
                echo "LDAP bind anonymous failed...";
            }

        }
        return $domainName;
    }
    
}
