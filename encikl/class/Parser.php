<?php
/**
 * Created by JetBrains PhpStorm.
 * User: gron
 * Date: 1/5/13
 * Time: 8:36 PM
 */
class Parser
{
    /**
     * Path to ini config
     */
    const INI_FILE = "config/parser.ini";

    /**
     * @var bool Use CURL or not. Use if module enabled
     */
    public static $curlEnabled = false;

    /**
     * @var array INI data
     */
    private $_iniData = array();

    /**
     * @var null|ParserDB PDO wrapper object
     */
    private $_db = null;

    /**
     * @var array Array with ini data. In format for example:
     * 'min_required' => (
     *      'non_extra' => (tinyint),
     *      'is_array'  => (tinyint),
     *      'regex'     => (string),
     *      'subs'      => (
     *          // for sub-arrays
     *          (string)    => array(
     *              'is_array'  => (tinyint)
     *              'subs'      => (
     *                  'level' => (
     *                      'regexp'    => (string),
     *                      'db_field'  => (string)
     *                  ),
     *                  ...
     *              )
     *          ),
     *          ...
     *          //or for single values
     *          (string)    => (
     *              'non_extra' => (tinyint),
     *              'regex'     => (string)
     *              'db_field'  => (string)
     *          )
     *      )
     * )
     */
    private $_iniRegExp = array();

    /**
     * Set load pahe method (curl/file_get_contents) and initialize ini array
     */
    public function __construct()
    {
        if (extension_loaded('curl')) {
            Parser::$curlEnabled = true;
        }

        $this->_getIniData();
    }

    /**
     * Setup DB
     */
    protected function setupDB(){
        $this->_db = ParserDB::getInstance();
        $this->_db->setIniData($this->_iniData);
    }

    /**
     * Main point to start
     * @return array
     */
    public function run()
    {
        $links = $this->_iniData['links']['links'];
        foreach ($links as $link) {
            $html = $this->_getPage($link);
            $html = $this->_getImgSrcOutsideTag($html);
            $text = $this->_stripAndIconv($html);
            $items = $this->_getItemsFromText($text);

            foreach($items as $item){
                $this->_db->insertItem($item);
                echo "Add item " . $item['itemName'] ." from link " . $link . PHP_EOL;
            }
        }
    }

    /**
     * Insert item from admin panel
     * @param $text text to insert from admin panel
     */
    public function runFromAdmin( $text ){
        $items = $this->_getItemsFromText($text);

        foreach($items as $item){
            $this->_db->insertItem($item);
            echo "Add item " . $item['itemName'] ." from admin text " . PHP_EOL;
        }
    }

    /**
     * Get INI array. By default use .ini file. Maybe in future move to XML
     */
    private function _getIniData(){
        $this->_iniData = parse_ini_file(__DIR__ . "/../" . Parser::INI_FILE, true);
        $resultArr = array();
        foreach ($this->_iniData as $key => $item) {
            if (isset($item['non_extra']) && ($item['non_extra'] == 1)) {
                $withSubs = $this->_getSubsData($this->_iniData, $item);
                $resultArr[$key] = $withSubs;
            }
        }

        $this->_iniRegExp = $resultArr;
    }

    /**
     * Set data to array with data from children's "subs" data. Nested tree.
     * @param $mainData - main array, where all leafs on start
     * @param $data - current leaf
     * @return mixed - leaf with sub-leafs
     */
    private function _getSubsData($mainData, $data)
    {
        if (isset($data['is_array']) && isset($data['subs']) && ($data['is_array'] == 1)) {
            $newArr = $data;
            unset($newArr['subs']);
            foreach ($data['subs'] as $item) {
                $newArr['subs'][$item] = $this->_getSubsData($mainData, $mainData[$item]);
            }
        } else {
            return $data;
        }

        return $newArr;
    }

    /**
     * Convert from windows to UTF-8, remove all JS, strip tags, remove newlines, extra spaces
     * @param $html
     * @return mixed
     */
    private function _stripAndIconv($html)
    {
        $newHtml = iconv("windows-1251", "utf-8", $html);
        $newHtml = preg_replace("/<script.*?>.*?<\/script>/sm", "", $newHtml);
        $newHtml = strip_tags($newHtml);
        $newHtml = preg_replace("/\n|\r/", "", $newHtml);
        $newHtml = preg_replace("/&nbsp;/", " ", $newHtml);
        $newHtml = preg_replace("/\s{2,}/", " ", $newHtml);
        return $newHtml;
    }

    /**
     * Get HTML source of page by CURL or file_get_contents
     * @param $url
     * @return mixed|string
     */
    private function _getPage($url)
    {
        if (Parser::$curlEnabled) {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_HEADER => false,
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true
            ));

            $html = curl_exec($curl);
            curl_close($curl);
        } else {
            $html = file_get_contents($url);
        }

        return $html;
    }

    /**
     * Move image sources outside tags, add @ to links, add @@ at the start of each item block
     * @param $html
     * @return mixed
     */
    private function _getImgSrcOutsideTag($html)
    {
        $regex = "/(<img.*?src=\W?([^>'\"]+)\W?.*?>)/i";
        $newHtml = preg_replace($regex, "$2$1", $html);

        $regex = "/(<a.*?>.*?<\/a>)/i";
        $newHtml = preg_replace($regex, "@$1@", $newHtml);

        $regex = "/(<td.*?bgcolor=\"?(?:#F3F1E7|#E6E2CE)\"?.*?>)/i";
        $newHtml = preg_replace($regex, "@@$1", $newHtml);

        return $newHtml;
    }

    /**
     * Get array with items or null in result
     * @param $text
     * @return array
     */
    private function _getItemsFromText($text)
    {
        //First lets get all item data from start to end
        $regexArray = array(
            '@@',
            '(?P<img>http:\/\/[-._\/\w]+\.gif)',
            '@(?P<itemName>[^@]+)@http:[-._\w\/]*align_(?P<align>.*?)\.\w{3}',
            '\(\s*Масса:\s*(?P<weight>[\d.]+)\s*\)',
            'Цена:\s*(?P<price>[\d.]+)\s*(?P<currency>[^\d]+\.)',
            'Долговечность\s*:\s*(?P<durabilityCurrent>\d+)\/(?P<durabilityMax>\d+)',
            '(?P<additionalInfo>[^@]*)',
        );

        $regex = implode("\s*", $regexArray);

        preg_match_all("/" . $regex . "/is", $text, $items, PREG_SET_ORDER);

        //Now in items we have items with img,name,align,weight,price,currency,durability and full info.
        //Now we need to loop through additional info and get information from ini file
        foreach($items as &$item){
            if(!empty($item['additionalInfo'])){
                foreach ($this->_iniRegExp as $key=>$regExpItem) {
                    $additionalMatch = array();
                    preg_match_all("/".$regExpItem['regex']."/i",$item['additionalInfo'],$additionalMatch);
                    if(isset($regExpItem['subs'])){
                        if(empty($additionalMatch['subs'])){
                            continue;
                        }
                        $res = $this->_getFromIniDataFromSubs($additionalMatch['subs'],$regExpItem['subs']);
                        $item[$key] = $res;
                    }else{
                        if(isset($additionalMatch['value'][0])){
                            $item[$key] = $additionalMatch['value'][0];
                        }
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Get data from additional info text by regex from ini file, recursive.
     * @param $additionalInfo
     * @param $subs
     * @return array
     */
    private function _getFromIniDataFromSubs($additionalInfo,$subs){
        $resultArr = array();
        foreach($subs as $key=>$value){
            if(isset($value['subs'])){
                // if we would need to use subgroups here - just use $resultArr[$key] instead of +=
                $resultArr += $this->_getFromIniDataFromSubs($additionalInfo,$value['subs']);
            }
            else{
                preg_match_all("/".$value['regex']."/",$additionalInfo[0],$match,PREG_SET_ORDER);
                if(!empty($match[0]['value'])){
                    $resultArr[$value['db_field']] = $match[0]['value'];
                }
            }
        }

        return $resultArr;
    }
}