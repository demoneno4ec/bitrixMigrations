<?php

namespace Sprint\Migration;


class Version20200129093620 extends Version
{
    private $name = 'Category';
    private $hl_table_name = 'hl_category';

    protected $description = '';

    /**
     * @return bool|void
     * @throws Exceptions\HelperException
     */
    public function up()
    {
        $helper = $this->getHelperManager();

        $hlblockId = $helper->Hlblock()->saveHlblock(
            [
                'NAME'       => $this->name,
                'TABLE_NAME' => $this->hl_table_name,
            ]
        );

        $helper->UserTypeEntity()->addUserTypeEntitiesIfNotExists(
            'HLBLOCK_'.$hlblockId,
            [
                [
                    'FIELD_NAME'   => 'UF_NAME',
                    'USER_TYPE_ID' => 'string',
                    'XML_ID'       => 'UF_TITLE',
                ],
                [
                    'USER_TYPE_ID' => 'string',
                    'FIELD_NAME'   => 'UF_XML_ID',
                    'XML_ID'       => 'UF_XML_ID',
                ],
            ]
        );
    }


    public function down()
    {
        $helper = $this->getHelperManager();
        try {
            $hlblockId = $helper->Hlblock()->getHlblockIdIfExists($this->name);

            $helper->UserTypeEntity()->deleteUserTypeEntitiesIfExists(
                'HLBLOCK_' . $hlblockId,
                [
                    'UF_NAME',
                    'UF_PRICE',
                    'UF_WEIGHT',
                    'UF_CREATED_AT',
                    'UF_UPDATED_AT',
                ]
            );

            $helper->Hlblock()->deleteHlblock($hlblockId);
            if ($hlblockId === false || $hlblockId <= 0) {
                $this->outError('Ошибка удаления справочника');
            } else {
                $this->outSuccess('Справочник удален');
            }
        } catch (Exceptions\HelperException $e) {
            $this->outError($e->getMessage());
        }
    }
}
