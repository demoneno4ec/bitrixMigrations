<?php require_once($_SERVER['DOCUMENT_ROOT']
    .'/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;


try {
    Loader::includeModule('highloadblock');
    Loader::includeModule('iblock');
} catch (LoaderException $e) {
    echo $e->getMessage();
    return false;
}

$news = new News();

/**
 * Пример записи новостей
 */

//$news->downloadNews();
//$news->updateCategoryes();
//$news->storeNews();

/**
 * Пример получения новостей по категории
 */

$news->getNews('Россия');


class NewsItem
{
    private $contentType = 'content';
    private $iblockCode = 'news';
    private $iblockID;

    private $title;
    private $link;
    private $description;
    private $pubDate;
    private $category;

    public function __construct($arParams)
    {
        $this->title = $arParams['title'];
        $this->link = $arParams['link'];
        $this->description = $arParams['description'];
        $this->pubDate = $arParams['pubDate'];
        $this->category = $arParams['category'];
        $this->setIblockID();
    }

    public function add($categoryXMLid)
    {
        $data = [
            'IBLOCK_TYPE'     => $this->contentType,
            'IBLOCK_ID'       => $this->iblockID,
            'NAME'            => $this->title,
            'PREVIEW_TEXT'    => $this->description,
            'PROPERTY_VALUES' => [
                'DATE_PUBLICATION' => $this->pubDate,
                'LINK'             => $this->link,
                'CATEGORY'         => $categoryXMLid
            ],
        ];

        $iblockElement = new CIBlockElement();
        $iblockElement->Add($data);
    }

    private function setIblockID()
    {
        $arFilter = [
            'CODE'   => $this->iblockCode,
            'TYPE'   => $this->contentType,
            'ACTIVE' => 'Y',
        ];

        $iblock = CIBlock::GetList([], $arFilter)->fetch();

        $this->iblockID = (int)$iblock['ID'];
    }

    public function getCategory()
    {
        return $this->category;
    }
}


class News
{
    private $contentType = 'content';
    private $iblockCode = 'news';
    private $needTags = ['title', 'link', 'description', 'pubDate', 'category'];

    private $newsResource = 'https://lenta.ru/rss';

    private $news = [];
    private $categoryes = [];
    private $keyCategory = 0;
    private $categoryMapping = [];
    private $categoryIDmapping = [];

    public function downloadNews()
    {
        // чтение XML
        $data = file_get_contents($this->newsResource);

        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $data, $values, $tags);
        xml_parser_free($parser);

        // проход через структуру
        foreach ($tags as $key => $val) {
            if ($key === 'item') {
                $molranges = $val;
                // каждая смежная пара значений массивов является верхней и
                // нижней границей определения новости
                for ($i = 0, $iMax = count($molranges); $i < $iMax; $i += 2) {
                    $offset = $molranges[$i] + 1;
                    $len = $molranges[$i + 1] - $offset;

                    $newsItemParam = [];
                    $mValues = array_slice($values, $offset, $len);

                    foreach ($mValues as $iValue) {
                        $tag = $iValue['tag'];
                        if (!$this->checkNeedTags($tag)) {
                            continue;
                        }

                        $clearValue = trim(strip_tags($iValue['value']));
                        $newsItemParam[$tag] = $clearValue;

                        if ($tag === 'category') {
                            $newsItemParam[$tag]
                                = $this->getCategory($clearValue);
                        }
                    }

                    $this->news[] = new NewsItem($newsItemParam);
                }
            } else {
                continue;
            }
        }
    }

    public function storeNews()
    {
        /** @var NewsItem $newsItem */
        foreach ($this->news as $newsItem) {
            $categoryID = $this->categoryIDmapping[$newsItem->getCategory()];
            $newsItem->add($categoryID);
        }
    }

    public function getNews(string $category = ''): array
    {
        $result = [];

        $hlCategory = new Category($category);
        $arSort = [];
        $arFilter = [
            'IBLOCK_CODE'       => $this->iblockCode,
            'IBLOCK_TYPE'       => $this->contentType,
            'ACTIVE'            => 'Y',
            'PROPERTY_CATEGORY' => $hlCategory->getCategoryByName()
        ];
        $arSelect = ['*'];
        $res = CIBlockElement::GetList($arSort, $arFilter, false, false,
            $arSelect);
        while ($arRes = $res->fetch()) {
            $result[$arRes['ID']] = $arRes;
        }

        return $result;
    }

    private function checkNeedTags($tag): bool
    {
        return in_array($tag, $this->needTags, true);
    }

    public function updateCategoryes()
    {
        foreach ($this->categoryes as $newsCategoryID => $category) {
            $newsCategoryID = (int)$newsCategoryID;
            $hlCategory = new Category($category);

            $categoryID = $hlCategory->check();
            if ($categoryID <= 0) {
                $hlCategory->add();
            }

            $this->categoryIDmapping[$newsCategoryID] = $hlCategory->getXmlID();
        }
    }

    private function getCategory(string $clearValue): int
    {
        if (!in_array($clearValue, $this->categoryes,
            true)
        ) {
            $this->categoryes[$this->keyCategory] = $clearValue;
            $this->categoryMapping[$clearValue] = $this->keyCategory;
            ++$this->keyCategory;
        }

        return $this->categoryMapping[$clearValue];
    }
}

class Category
{
    private $hl_table_name = 'hl_category';
    private $entity_data_class;

    private $title;

    private $xmlID;


    /**
     * @var int|string
     */
    private $newsCategoryID;


    public function getCategoryByName(): string
    {
        try {
            $arData = $this->entity_data_class::getList([
                // Задаем параметры фильтра выборки
                'select' => ['*'],
                'order'  => ['ID' => 'ASC'],
                'filter' => ['UF_NAME' => $this->title]
            ])->fetch();
            return $arData['UF_XML_ID'] ?? '';
        } catch (ObjectPropertyException $e) {
            return '';
        } catch (ArgumentException $e) {
            return '';
        } catch (SystemException $e) {
            return '';
        }

    }

    public function __construct($title)
    {
        try {
            $hlblock = HL\HighloadBlockTable::getList([
                'filter' => ['TABLE_NAME' => $this->hl_table_name]
            ])->fetch();
            $entity = HL\HighloadBlockTable::compileEntity($hlblock);
            $this->entity_data_class = $entity->getDataClass();

            $this->setTitle($title);
            $this->translateXmlID($title);
        } catch (ObjectPropertyException $e) {
            echo $e->getMessage();
        } catch (ArgumentException $e) {
            echo $e->getMessage();
        } catch (SystemException $e) {
            echo $e->getMessage();
        }
    }

    private function translateXmlID($title)
    {
        $arParams = [
            'replace_space' => '_',
            'replace_other' => '',
        ];
        $xmlID = Cutil::translit($title, 'ru', $arParams);

        $this->setXmlID($xmlID);
    }

    public function add(): int
    {
        $data = array(
            'UF_NAME'   => $this->title,
            'UF_XML_ID' => $this->xmlID,
        );

        try {
            return $this->entity_data_class::add($data)->getId();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function check(): int
    {
        try {
            $arData = $this->entity_data_class::getList([
                // Задаем параметры фильтра выборки
                'select' => ['*'],
                'order'  => ['ID' => 'ASC'],
                'filter' => ['UF_NAME' => $this->title]
            ])->fetch();
            return (int)$arData['ID'];
        } catch (ObjectPropertyException $e) {
            return 0;
        } catch (ArgumentException $e) {
            return 0;
        } catch (SystemException $e) {
            return 0;
        }
    }

    /**
     * @param  string  $xmlID
     */
    public function setXmlID($xmlID)
    {
        $this->xmlID = $xmlID;
    }

    /**
     * @param  string  $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @param  int  $newsCategoryID
     */
    public function setNewsCategoryID(int $newsCategoryID)
    {
        $this->newsCategoryID = $newsCategoryID;
    }

    /**
     * @return mixed
     */
    public function getXmlID()
    {
        return $this->xmlID;
    }
}