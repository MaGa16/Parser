<?php
/**
 * Created by PhpStorm.
 * User: MaratPC
 * Date: 28.03.2019
 * Time: 14:19
 */
require_once ("vendor/autoload.php");

class parserEngine{
    const HOST ="mysql:host=localhost;dbname=parserdb";
    const USERNAME = "root";
    const PASSWORD = "";
    const _PAGE = "&page=";
    private $db;
    private $tmp = [];
    private $urlPath = "https://kazan.hh.ru/search/vacancy?area=1624&clusters=true&enable_snippets=true&text=php&experience=noExperience&from=cluster_experience";
    private $standartArr = [];
    private $newArr = [];
    private $oldArr = [];
    private $resultOld  = [];
    private $resultNew = [];
    private $finalArr = [];

function __construct()
{
    $this->createDB();
    $this->createTable();
    $this->getContent();
    $this->readFromBase();
    $this->checkDate($this->oldArr);
    $this->checkDate($this->newArr);
    $this->diffArr();
    $this->newOrOld();
    $this->getDate($this->resultOld);
    $this->getDate($this->resultNew);
    $this->mergeArr($this->oldArr,$this->resultOld,$this->newArr,$this->resultNew);
    $this->insertInBase();

}
//создает подключение
    private function createDB ()
    {
        try{
            $this->db = new PDO(self::HOST,  self::USERNAME, self::PASSWORD);
        }catch (PDOException $e){
            $e->getMessage();
        }
    }
//создает таблицу
    private function createTable()
    {
        $tableExists = $this->db->query("SHOW TABLES LIKE 'actualBase'")->rowCount() > 0;
        if(!$tableExists) {
    $sqlquery = "CREATE TABLE `actualBase`  
( `ID` INT NOT NULL AUTO_INCREMENT,
 `vacansy` VARCHAR(100) ,
      `url` VARCHAR(100) NOT NULL,
      `company` VARCHAR(40) NOT NULL,
  `description` VARCHAR(600) NOT NULL,
  `salary` VARCHAR(40) NOT NULL,
  `neworold` VARCHAR(40) NOT NULL,
  `creationdate` VARCHAR(40) NOT NULL,
   PRIMARY KEY (`ID`)) ";
    $this->db->exec($sqlquery);
} else {
    echo "";
}
    }
//создает массив из новых вакансий
    private function getContent(){
    for($i=0;$i<=20;$i++) {
    $html = file_get_contents($this->urlPath.self::_PAGE.$i);
        phpQuery::newDocument($html);
        $findItem = pq(".vacancy-serp-item ");

        foreach ($findItem as $link) {

            $link = pq($link);

            $this->tmp[] = array(
                "vacName" => $link->find(".resume-search-item__name a")->text(),
                "url" => $link->find(".resume-search-item__name a")->attr("href"),
                "description" => $link->find(".vacancy-serp-item__info")->text(),
                "nameOfComp" => $link->find(".vacancy-serp-item__meta-info")->text(),
                "salary" => $link->find(".vacancy-serp-item__compensation")->text(),
                "neworold" => "",
                "date" => ""
            );
        }
        phpQuery::unloadDocuments();

       }
}
//создает массивы из базы данных, сортируя по новым и старым вакансиям
    private function readFromBase()
{
        $query = "SELECT * FROM `actualBase`";
$sth = $this->db->prepare($query);
$sth->execute();
$result = $sth->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
    if ($row['neworold'] == "") {
        $this->standartArr [] = array(
            "vacName" => $row['vacansy'],
            "url" => $row['url'],
            "description" => $row['description'],
            "nameOfComp" => $row['company'],
            "salary" => $row['salary'],
            "date" => $row['creationdate'],
            "neworold" => $row['neworold']
        );
    }

if ($row['neworold'] == "new") {
    $this->newArr[] = array(
        "vacName" => $row['vacansy'],
        "url" => $row['url'],
        "description" => $row['description'],
        "nameOfComp" => $row['company'],
        "salary" => $row['salary'],
        "date" => $row['creationdate'],
        "neworold" => $row['neworold']
    );
}
    if ($row['neworold'] == "old") {
        $this->oldArr[] = array(
            "vacName" => $row['vacansy'],
            "url" => $row['url'],
            "description" => $row['description'],
            "nameOfComp" => $row['company'],
            "salary" => $row['salary'],
            "date" => $row['creationdate'],
            "neworold" => $row['neworold']
        );
    }

}
}
//удаляет старые значения
    private function checkDate(&$arr){
foreach ($arr as $key => &$value){
        if(!empty($value['date'])){
            if ($value['date'] < date("F j, Y, g:i a")) {
                echo $value['date'];
                echo date("F j, Y, g:i a");
                echo "<br/>";
                echo "<br/>";
                unset($arr[$key]);
            }
        }
}
}
//сравнивает массивы
    private function diffArr()
{
    foreach($this->standartArr as $value){
        $i = 0;
        foreach ($this->tmp as $item){
            if ($value['url'] == $item['url']){
                $i++;
            }
}
if($i==0) {
    $this->resultOld[] = $value;
}
}

    foreach($this->tmp as $value){
        $i = 0;
        foreach ($this->standartArr as $item){
            if ($value['url']==$item['url']){
                $i++;
            }
        }
        if($i==0) {
            $this->resultNew[] = $value;
        }
    }
}

    private function newOrOld()
{
    foreach($this->resultOld as &$value){
        $value["neworold"] = 'old';
    }
    foreach($this->resultNew as &$value){
       $value["neworold"] = 'new';
    }
    return $this->resultNew;
}

    private function getDate(&$arr)
{
    foreach($arr as &$value){
    $value["date"] = date("F j, Y, g:i a",time()+60*60*24*3);
}
}

    private function mergeArr($arr1, $arr2, $arr3, $arr4){
        $oldResults = array_merge($arr1, $arr2);
        $resultNew = array_merge($arr3, $arr4);
        $this->finalArr = array_merge($resultNew, $this->tmp, $oldResults);
}

    private function insertInBase(){
    $query = "DELETE FROM `actualBase`" ;
    $this->db->query($query);
        foreach ($this->finalArr as $value){
                $vac = $value['vacName'];
                $url = $value['url'];
                $description = $value['description'];
    $name = $value['nameOfComp'];
    $salary = $value['salary'];
    $newOrOld  = $value['neworold'];
    $date = $value['date'];

        try {
                $sqlquery = "INSERT INTO `actualBase` (
vacansy,
 url,
  company,
   description,
    salary,
    neworold,
    creationdate) 
    VALUES (
    '$vac',
    '$url',
    '$name',
    '$description',
    '$salary',
    '$newOrOld',
    '$date'
    )";
                $this->db->query($sqlquery);
                //echo "успех";
            } catch (Exception $e){
                $e->getMessage("error");
            }

}
}

public function showTable()
{
    //var_dump($this->finalArr);
        foreach($this->finalArr as $value) {
            if($value["neworold"] == "old"){
                echo "<tr bgcolor='red'>";
            }if ($value["neworold"] == "new"){
                echo "<tr bgcolor='green'>";
            }if ($value["neworold"] == ""){
            echo "<tr>";
            }
            foreach ($value as $item) {
                if($item == $value["vacName"]) {
                    echo "<th>";
                    echo "<a href='";
                    echo $value["url"];
                    echo "'>";
                    echo $value["vacName"];
                    echo "</a>";
                    echo "</th>";
                }
                if($item == $value["vacName"] || $item == $value["url"]){
                    echo "";
                }
                    else {
                    echo "<th>";
                    echo "<a>";
                    echo $item;
                    echo "</a>";
                    echo "</th>";
                }
            }
            echo "</tr>";
        }


}
}



$parser = new parserEngine();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
<table border="solid">
    <tr>
        <th>
            Вакансия
        </th>
        <th>
            Описание
        </th>
        <th>
            Компания
        </th>
        <th>
            Зарплата
        </th>
    </tr>
   <?php $parser->showTable() ?>

</table>
</body>
</html>