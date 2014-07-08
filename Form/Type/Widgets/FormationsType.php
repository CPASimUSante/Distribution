<?php

namespace Icap\PortfolioBundle\Form\Type\Widgets;

use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;

/**
 * @DI\FormType
 */
class FormationsType extends AbstractWidgetType
{
    /** @var \Symfony\Bundle\FrameworkBundle\Translation\Translator */
    private $translator;

    /** @var \Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler  */
    private $platformConfigHandler;

    /**
     * @DI\InjectParams({
     *     "translator"            = @DI\Inject("translator"),
     *     "platformConfigHandler" = @DI\Inject("claroline.config.platform_config_handler")
     * })
     */
    public function __construct(Translator $translator, PlatformConfigurationHandler $platformConfigHandler)
    {
        $this->translator            = $translator;
        $this->platformConfigHandler = $platformConfigHandler;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $language   = $this->platformConfigHandler->getParameter('locale_language');
        $dateFormat = $this->translator->trans('datepicker_date_format', array(), 'icap_portfolio');

        $builder
            ->add('name', 'text')
            ->add('startDate', 'datepicker',
                array(
                    'required' => false,
                    'language' => $language,
                    'format'   => $dateFormat
               )
            )
            ->add('endDate', 'datepicker',
                array(
                    'required' => false,
                    'language' => $language,
                    'format'   => $dateFormat
               )
            )
            ->add('children', 'collection',
                array(
                    'type'          => 'icap_portfolio_widget_form_formations_formation',
                    'by_reference'  => false,
                    'allow_add'     => true,
                    'allow_delete'  => true,
                    'property_path' => 'resources'
                )
            );
    }

    public function getName()
    {
        return 'icap_portfolio_widget_form_formations';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class'         => 'Icap\PortfolioBundle\Entity\Widget\FormationsWidget',
                'translation_domain' => 'icap_portfolio',
                'csrf_protection'    => false,
            )
        );
    }
}
