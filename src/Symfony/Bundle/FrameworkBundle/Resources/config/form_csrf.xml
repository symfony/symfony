<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <parameters>
        <parameter key="form.csrf_provider.class">Symfony\Component\Form\Extension\Csrf\CsrfProvider\SessionCsrfProvider</parameter>
    </parameters>

    <services>
        <service id="form.csrf_provider" class="%form.csrf_provider.class%">
            <argument type="service" id="session" />
            <argument>%kernel.secret%</argument>
        </service>

        <service id="form.type_extension.csrf" class="Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension">
            <tag name="form.type_extension" alias="form" />
            <argument type="service" id="form.csrf_provider" />
            <argument>%form.type_extension.csrf.enabled%</argument>
            <argument>%form.type_extension.csrf.field_name%</argument>
            <argument type="service" id="translator.default" />
            <argument>%validator.translation_domain%</argument>
        </service>
    </services>
</container>
