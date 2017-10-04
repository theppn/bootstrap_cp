<?php
// src/AppBundle/Form/PushNotificationType.php
namespace AppBundle\Form;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;

class PushNotificationType extends AbstractType {
    public function __construct($options = null) {
        $this->options = $options;
    }
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder
            ->add('title', TextType::class, array(
                'required' => true,
                'constraints' => array(
                    new Assert\NotBlank()
                )
            ))
            ->add('message', TextType::class, array(
                'required' => true,
                'constraints' => array(
                    new Assert\NotBlank()
                )
            ))
            ->add('icon', TextType::class, array(
                'required' => false
            ))
            ->add('url', TextType::class, array(
                'required' => false,
                'constraints' => array(
                    new Assert\Url()
            )
            ))
            ->add('timeout', TextType::class, array(
                'required' => true,
                'constraints' => array(
                    new Assert\GreaterThan(array(
                        'value' => 0,
                    ))
                )
            ))
            ->add('save', SubmitType::class)
            ->getForm();
    }
}

?>
