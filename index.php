<?php 
include 'librairies/simple_html_dom.php';


if (isset($_GET['string']) && isset($_GET['url'])) {
    new Finder($_GET['string'], $_GET['url']);
} else { ?>
    <h1>This is a small form to explain how Eventus-lite Api works</h1>
    <form action="" method="get">
        String to parse FFHB's website: <br>
        <input type="text" name="string"><br><br>
        FFHB's url that contains result: <br>
        <input type="url" name="url"><br> <br>
        <input type="submit" value="Submit">
    </form>
<?php
}

/**
* Finder is a class that allows you to manage all synchronization actions of matches.
*
* @access public
*/
class Finder {
    function __construct($string, $url) { 
        header('Content-Type: application/json');
        echo $this->getMatches($string, urldecode($url));        
    }   

    /**
    * Synchronize matches by team with FFHB website informations
    *
    * @param string  String that will be use to parse FFHB website    
    * @param string  Url of the FFHB website that contain result
    * @return string[]
    * @access public
    */
    private function getMatches($string, $url){
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $output = curl_exec($ch);
            if(curl_errno($ch)) {
                return json_encode(["error"=> "Error cUrl: ".curl_errno($ch)." : Unable to access this page"]);
            } else {
                $html = str_get_html($output);
                if ($html->getElementById('ul#journeelist') == null ){
                    return json_encode(["error"=> "Can't find match informations"]);
                } else {
                    $allMatches = [];
                    foreach($html->find('div.round tr') as $row) {
                        if ( strpos( strtolower($row), strtolower($string) ) ){
                            $teamInfos = [
                                "position" => 
                                    intval($row->find('td.num',0)->plaintext),
                                "points" => 
                                    intval($row->find('td.pts',0)->plaintext),
                                "played" => 
                                    intval($row->find('td',3)->plaintext),
                                "won" => 
                                    intval($row->find('td',4)->plaintext),
                                "draw" => 
                                    intval($row->find('td',5)->plaintext),
                                "lose" => 
                                    intval($row->find('td',6)->plaintext),
                                "butPlus" => 
                                    intval($row->find('td',7)->plaintext),
                                "butMinus" => 
                                    intval($row->find('td',8)->plaintext),
                                "diff" => 
                                    intval($row->find('td',9)->plaintext)
                            ];
                        }
                    }

                    foreach($html->getElementById('ul#journeelist')->find('.touchcarousel-item') as $matchDay => $rows) {
                        $clubFound = false;
                        $prevMatchDay = $matchDay;
                        $numMatch = 0;
                        foreach($rows->find('tr') as $row) {
                            if ( strpos( strtolower($row->find('td.eq',0)), strtolower($string) ) ){
                                $clubFound = true;
                                $fullAdress = explode("#/#", $row->find('td.info a',0)->attr['data-text-tooltip']);
                                $fullReferees = explode("#/#", $row->find('td.arb a',0)->attr['data-text-tooltip']);
                                $fullDate = explode("<br>", $row->find('td.date',0)->innertext);
                                if ($matchDay == $prevMatchDay) {
                                    $numMatch++;
                                }
                                if ( explode(" -  ", $row->find('td.eq p',0)->plaintext)[1] && explode(" -  ", $row->find('td.eq p',1)->plaintext)[1]) {
                                    $allMatches[] = 
                                        [
                                            'day' => 
                                                ($matchDay+1),
                                            'num' => 
                                                $numMatch,
                                            'date' => 
                                                strlen($fullDate[1])>0 ? date_create_from_format('d/m/Y', $fullDate[0])->format('Y-m-d') : null,
                                            'hourStart' => 
                                                strlen($fullDate[1])>0 ? date_create_from_format('H:i:s', $fullDate[1])->format('H:i:s') : null,
                                            'ext' => 
                                                strpos(strtolower($this->stripAccents(explode(" -  ", $row->find('td.eq p',0)->plaintext)[1])),strtolower($this->stripAccents($string))) !== false ? FALSE : TRUE,
                                            // 'fdm' => 
                                            //     $row->find('td.fdm a',0) && $row->find('td.fdm a',0)->attr['href'] ? 'http://www.ff-handball.org'.$row->find('td.fdm a',0)->attr['href'] : null,
                                            'localTeam' => [
                                                'name' => 
                                                    $this->getCleanString(explode(" -  ", $row->find('td.eq p',0)->plaintext)[1]),
                                                'score' => 
                                                    $row->find('td.eq p',0)->find('strong',0)->plaintext ? intval($row->find('td.eq p',0)->find('strong',0)->plaintext) : null
                                            ],
                                            'visitingTeam' => [
                                                'name' => 
                                                    $this->getCleanString(explode(" -  ", $row->find('td.eq p',1)->plaintext)[1]),
                                                'score' => 
                                                    $row->find('td.eq p',1)->find('strong',0)->plaintext ? intval($row->find('td.eq p',1)->find('strong',0)->plaintext) : null
                                            ],
                                            'referees' => [
                                                1 => 
                                                    count($fullReferees)-2 && $fullReferees[count($fullReferees)-2] && $fullReferees[count($fullReferees)-2] != "Aucun arbitre renseigné" ? $fullReferees[count($fullReferees)-2] : null,
                                                2 => 
                                                    count($fullReferees)-3 && $fullReferees[count($fullReferees)-3] ? $fullReferees[count($fullReferees)-3] : null
                                            ],
                                            'address' => [
                                                'street' => 
                                                count($fullAdress)-3 > -1 && $this->getCleanString($fullAdress[count($fullAdress)-3]) ? $this->getCleanString($fullAdress[count($fullAdress)-3]) : null,
                                                'city' => 
                                                    count($fullAdress)-2 > -1 && $this->getCleanString($fullAdress[count($fullAdress)-2]) ? $this->getCleanString($fullAdress[count($fullAdress)-2]) : null,
                                                'gym' => 
                                                    count($fullAdress)-4 > -1 && $this->getCleanString($fullAdress[count($fullAdress)-4]) ? $this->getCleanString($fullAdress[count($fullAdress)-4]) : null
                                            ]                                            
                                        ];  
                                }                
                            }                                        
                        }
                        // if (!$clubFound){
                        //     return json_encode(["error"=> "Can't find the string in matches list"]);
                        // } 
                    }
                    $html->clear();
                    curl_close($ch);  
                }  
                //return var_dump(["infos"=> $teamInfos, "matches" => $allMatches]);
                return json_encode(["infos"=> $teamInfos, "matches" => $allMatches]);
            }    
    }

    /**
    * Transform character with accent to characters without accents
    *
    * @param string    String to strip accents
    * @return Match[]  String with accents strip 
    * @access public
    */
    private function stripAccents($str) {
        return strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }

    /**
    * Add log in file log
    *
    * @param string    String of the log to be added
    * @return void 
    * @access private
    */
    private function addLog($myLog){    
        date_default_timezone_set("Europe/Paris");
        file_put_contents(plugin_dir_path( __FILE__ ).'../../finder.log', "[".date("d/m/y H:i:s")."] ".$myLog."\n", FILE_APPEND);
    }

    /**
    * Transfom string to an UTF-8 string
    *
    * @param string     String to be updated
    * @return string      String updated
    * @access private
    */
    private function getCleanString($myString){
        if ($myString[0] == " "){
            $myString = substr($myString, 1);
        }
        return mb_convert_case(mb_strtolower(iconv("UTF-8", "ISO-8859-1//TRANSLIT", $myString)), MB_CASE_TITLE, "UTF-8");
    }
}
?>