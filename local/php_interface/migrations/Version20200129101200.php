<?php

namespace Sprint\Migration;


class Version20200129101200 extends Version
{
    private $iblockType = 'content';
    private $name = 'Новости';
    private $code = 'news';

    protected $description = 'Добавляем инфоблок новости';

    /**
     * @return bool|void
     * @throws Exceptions\HelperException
     */
    public function up()
    {
        $helper = $this->getHelperManager();

        $helper->Iblock()->saveIblockType([
            'ID'   => $this->iblockType,
            'LANG' => [
                'ru' => [
                    'NAME'         => 'Контент',
                    'SECTION_NAME' => 'Разделы',
                    'ELEMENT_NAME' => 'Новости',
                ],
            ],
        ]);

        $iblockId = $helper->Iblock()->saveIblock([
            'NAME'            => $this->name,
            'CODE'            => $this->code,
            'LID'             => ['s1'],
            'IBLOCK_TYPE_ID'  => $this->iblockType,
            'LIST_PAGE_URL'   => '',
            'DETAIL_PAGE_URL' => '',
        ]);

        $helper->Iblock()->saveProperty(
            $iblockId,
            [
                'NAME' => 'Дата публикации',
                'CODE' => 'DATE_PUBLICATION',
            ]
        );
        $helper->Iblock()->saveProperty(
            $iblockId,
            [
                'NAME' => 'Ссылка',
                'CODE' => 'LINK',
            ]
        );
        $helper->Iblock()->saveProperty(
            $iblockId,
            [
                'NAME'               => 'Категория',
                'CODE'               => 'CATEGORY',
                'USER_TYPE'          => 'directory',
                'FILTRABLE'          => 'Y',
                'LINK_IBLOCK_ID'     => 0,
                'USER_TYPE_SETTINGS' => [
                    'size'       => '1',
                    'width'      => '0',
                    'group'      => 'N',
                    'multiple'   => 'N',
                    'TABLE_NAME' => 'hl_category'
                ],
            ]
        );

        $this->outSuccess('Инфоблок создан');
    }

    /**
     * @return bool|void
     */
    public function down()
    {
        $helper = $this->getHelperManager();
        try {
            $ok = $helper->Iblock()->deleteIblockIfExists($this->code);
            if ($ok) {
                $this->outSuccess('Инфоблок удален');
            } else {
                $this->outError('Ошибка удаления инфоблока');
            }
        } catch (Exceptions\HelperException $e) {
            $this->outError($e->getMessage());
        }

    }
}
