<?php declare(strict_types=1);
namespace CartoAffect\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'name' => 'cartoaffect-mail',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Mail du compte anonyme', // @translate
                    'info' => 'Nécessaire à la création de données anonyme.', // @translate
                ],
                'attributes' => [
                    'id' => 'cartoaffect_mail',
                ],
            ])
            ->add([
                'name' => 'cartoaffect-pwd',
                'type' => Element\Password::class,
                'options' => [
                    'label' => 'Mot de passe du compte anonyme', // @translate
                    'info' => 'Nécessaire à la création de données anonyme.', // @translate
                ],
                'attributes' => [
                    'id' => 'cartoaffect_pwd',
                ],
            ]);
    }
}
