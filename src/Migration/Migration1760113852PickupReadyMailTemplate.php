<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1760113852PickupReadyMailTemplate extends MigrationStep
{
    // Existing template constants
    final const PICKUP_READY_TEMPLATE_ID = 'b8826ee629d39b4ecf88c984424e8732';
    final const PICKUP_READY_TEMPLATE_TYPE_ID = '582ad2d4dfc0b95af9ac16b4d70a3fb9';

    // New admin notification constants
    final const ADMIN_ORDER_PLACED_TEMPLATE_ID = 'c8826ee629d39b4ecf88c984424e8733';
    final const ADMIN_ORDER_PLACED_TEMPLATE_TYPE_ID = '682ad2d4dfc0b95af9ac16b4d70a3fba';

    public function getCreationTimestamp(): int
    {
        return 1760113852;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $this->createPickupReadyTemplate($connection);
        $this->createAdminOrderPlacedTemplate($connection);
    }

    /**
     * @throws Exception
     */
    private function createPickupReadyTemplate(Connection $connection): void
    {
        // Create mail template type
        $this->createMailTemplateType(
            $connection,
            self::PICKUP_READY_TEMPLATE_TYPE_ID,
            'order_delivery.state.ready_for_pickup',
            [
                'en-GB' => 'Order ready for pickup',
                'de-DE' => 'Bestellung abholbereit'
            ]
        );

        // Create mail template
        $this->createMailTemplate(
            $connection,
            self::PICKUP_READY_TEMPLATE_ID,
            self::PICKUP_READY_TEMPLATE_TYPE_ID,
            [
                'en-GB' => [
                    'senderName' => '{{ salesChannel.name }}',
                    'subject' => 'Order ready for pickup',
                    'description' => 'Your order is ready for pickup',
                    'contentHtml' => $this->getPickupReadyContentHtmlEn(),
                    'contentPlain' => $this->getPickupReadyContentPlainEn(),
                ],
                'de-DE' => [
                    'senderName' => '{{ salesChannel.name }}',
                    'subject' => 'Bestellung abholbereit',
                    'description' => 'Ihre Bestellung ist abholbereit',
                    'contentHtml' => $this->getPickupReadyContentHtmlDe(),
                    'contentPlain' => $this->getPickupReadyContentPlainDe(),
                ]
            ]
        );
    }

    /**
     * @throws Exception
     */
    private function createAdminOrderPlacedTemplate(Connection $connection): void
    {
        // Create mail template type for admin notification
        $this->createMailTemplateType(
            $connection,
            self::ADMIN_ORDER_PLACED_TEMPLATE_TYPE_ID,
            'order.state.placed.admin',
            [
                'en-GB' => 'New Pickup Order Placed - Admin Notification',
                'de-DE' => 'Neue Abholbestellung aufgegeben - Admin Benachrichtigung'
            ]
        );

        // Create mail template for admin notification
        $this->createMailTemplate(
            $connection,
            self::ADMIN_ORDER_PLACED_TEMPLATE_ID,
            self::ADMIN_ORDER_PLACED_TEMPLATE_TYPE_ID,
            [
                'en-GB' => [
                    'senderName' => '{{ salesChannel.name }}',
                    'subject' => 'New Order Received - #{{ order.orderNumber }}',
                    'description' => 'Admin notification when a new order is placed',
                    'contentHtml' => $this->getAdminOrderPlacedContentHtmlEn(),
                    'contentPlain' => $this->getAdminOrderPlacedContentPlainEn(),
                ],
                'de-DE' => [
                    'senderName' => '{{ salesChannel.name }}',
                    'subject' => 'Neue Bestellung erhalten - #{{ order.orderNumber }}',
                    'description' => 'Admin Benachrichtigung bei neuer Bestellung',
                    'contentHtml' => $this->getAdminOrderPlacedContentHtmlDe(),
                    'contentPlain' => $this->getAdminOrderPlacedContentPlainDe(),
                ]
            ]
        );
    }

    /**
     * @throws Exception
     */
    private function createMailTemplateType(Connection $connection, string $typeId, string $technicalName, array $translations): void
    {
        $mailTemplateTypeId = Uuid::fromHexToBytes($typeId);

        if (!$this->mailTemplateTypeExists($connection, $mailTemplateTypeId, $technicalName)) {
            $connection->insert('mail_template_type', [
                'id' => $mailTemplateTypeId,
                'technical_name' => $technicalName,
                'available_entities' => json_encode(['order' => 'order', 'salesChannel' => 'sales_channel']),
                'template_data' => '{"order":{"orderNumber":"10060","orderCustomer":{"firstName":"Max","lastName":"Mustermann"},"amountTotal":150.50,"lineItems":[{"label":"Product 1","quantity":1,"price":{"unitPrice":100.0}}]},"salesChannel":{"name":"Storefront"}}',
                'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        foreach ($translations as $locale => $name) {
            $languageId = $this->getLanguageIdByLocale($connection, $locale);
            if (!empty($languageId)) {
                if (!$this->mailTemplateTypeTranslationExists($connection, $mailTemplateTypeId, $languageId)) {
                    $connection->insert('mail_template_type_translation', [
                        'mail_template_type_id' => $mailTemplateTypeId,
                        'language_id' => $languageId,
                        'name' => $name,
                        'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function createMailTemplate(Connection $connection, string $templateId, string $templateTypeId, array $translations): void
    {
        $mailTemplateId = Uuid::fromHexToBytes($templateId);
        $mailTemplateTypeId = Uuid::fromHexToBytes($templateTypeId);

        if (!$this->mailTemplateExists($connection, $mailTemplateId)) {
            $connection->insert('mail_template', [
                'id' => $mailTemplateId,
                'mail_template_type_id' => $mailTemplateTypeId,
                'system_default' => 0,
                'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        foreach ($translations as $locale => $translation) {
            $languageId = $this->getLanguageIdByLocale($connection, $locale);
            if (!empty($languageId)) {
                if (!$this->mailTemplateTranslationExists($connection, $mailTemplateId, $languageId)) {
                    $connection->insert('mail_template_translation', [
                        'mail_template_id' => $mailTemplateId,
                        'language_id' => $languageId,
                        'sender_name' => $translation['senderName'],
                        'subject' => $translation['subject'],
                        'description' => $translation['description'],
                        'content_html' => $translation['contentHtml'],
                        'content_plain' => $translation['contentPlain'],
                        'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function mailTemplateTypeExists(Connection $connection, string $mailTemplateTypeId, string $technicalName): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM `mail_template_type`
            WHERE `id` = :id OR `technical_name` = :technicalName
            LIMIT 1
        SQL;

        return (bool) $connection->executeQuery($sql, [
            'id' => $mailTemplateTypeId,
            'technicalName' => $technicalName,
        ])->fetchOne();
    }

    /**
     * @throws Exception
     */
    private function mailTemplateTypeTranslationExists(Connection $connection, string $mailTemplateTypeId, string $languageId): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM `mail_template_type_translation`
            WHERE `mail_template_type_id` = :mailTemplateTypeId
              AND `language_id` = :languageId
            LIMIT 1
        SQL;

        return (bool) $connection->executeQuery($sql, [
            'mailTemplateTypeId' => $mailTemplateTypeId,
            'languageId' => $languageId,
        ])->fetchOne();
    }

    /**
     * @throws Exception
     */
    private function mailTemplateExists(Connection $connection, string $mailTemplateId): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM `mail_template`
            WHERE `id` = :id
            LIMIT 1
        SQL;

        return (bool) $connection->executeQuery($sql, [
            'id' => $mailTemplateId,
        ])->fetchOne();
    }

    /**
     * @throws Exception
     */
    private function mailTemplateTranslationExists(Connection $connection, string $mailTemplateId, string $languageId): bool
    {
        $sql = <<<SQL
            SELECT 1
            FROM `mail_template_translation`
            WHERE `mail_template_id` = :mailTemplateId
              AND `language_id` = :languageId
            LIMIT 1
        SQL;

        return (bool) $connection->executeQuery($sql, [
            'mailTemplateId' => $mailTemplateId,
            'languageId' => $languageId,
        ])->fetchOne();
    }

    /**
     * @throws Exception
     */
    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<SQL
            SELECT `language`.`id`
            FROM `language`
            INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
            WHERE `locale`.`code` = :code
        SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();

        return \is_string($languageId) && $languageId !== '' ? $languageId : null;
    }

    // Pickup Ready Content - English
    private function getPickupReadyContentHtmlEn(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">
            <p>
                Dear {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
                <br><br>
                We are pleased to inform you that your order {{ order.orderNumber }} is now ready for pickup.<br><br>
                Please bring a valid ID and your order confirmation when collecting your order.<br><br>
                If you have any questions, feel free to contact us.<br><br>
                Best regards,<br>
                Your {{ salesChannel.name }} Team
            </p>
        </div>
        MAIL;
    }

    private function getPickupReadyContentPlainEn(): string
    {
        return <<<MAIL
        Dear {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        
        Your order {{ order.orderNumber }} is now ready for pickup. 
        Please bring a valid ID and your order confirmation when collecting your order.
        
        Best regards,
        Your {{ salesChannel.name }} Team
        MAIL;
    }

    // Pickup Ready Content - German
    private function getPickupReadyContentHtmlDe(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">
            <p>
                Sehr geehrte/r {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
                <br><br>
                wir freuen uns, Ihnen mitteilen zu können, dass Ihre Bestellung {{ order.orderNumber }} ab sofort zur Abholung bereitsteht.<br><br>
                Bitte bringen Sie zur Abholung einen gültigen Ausweis sowie Ihre Bestellbestätigung mit.<br><br>
                Bei Rückfragen stehen wir Ihnen gerne zur Verfügung.<br><br>
                Mit freundlichen Grüßen<br>
                Ihr {{ salesChannel.name }} Team
            </p>
        </div>
        MAIL;
    }

    private function getPickupReadyContentPlainDe(): string
    {
        return <<<MAIL
        Sehr geehrte/r {{ order.orderCustomer.firstName }} {{ order.orderCustomer.lastName }},
        
        Ihre Bestellung {{ order.orderNumber }} ist jetzt abholbereit. 
        Bitte bringen Sie zur Abholung einen gültigen Ausweis sowie Ihre Bestellbestätigung mit.
        
        Mit freundlichen Grüßen,
        Ihr {{ salesChannel.name }} Team
        MAIL;
    }

    // Admin Order Placed Content - English
    private function getAdminOrderPlacedContentHtmlEn(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">
            {% set currencyIsoCode = order.currency.isoCode %}
            We have received a new pickup order from {{ order.orderDateTime|format_datetime('medium', 'short', locale='en-GB') }}.<br>
            <br>
            Order number: {{ order.orderNumber }}<br>
            <br>
            Please prepare the order for the customer to pick up. <br>
            <br>
            
            <strong>Information on your order:</strong><br>
            <br>
        
            <table border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
                <tr>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Prod. no.</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Product image</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Description</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
                </tr>
        
                {% for lineItem in order.nestedLineItems %}
                    {% set nestingLevel = 0 %}
                    {% set nestedItem = lineItem %}
                    {% block lineItem %}
                        <tr>
                            <td>{% if nestedItem.payload.productNumber is defined %}{{ nestedItem.payload.productNumber|u.wordwrap(80) }}{% endif %}</td>
                            <td>{% if nestedItem.cover is defined and nestedItem.cover is not null %}<img src="{{ nestedItem.cover.url }}" width="75" height="auto"/>{% endif %}</td>
                            <td>
                                {% if nestingLevel > 0 %}
                                    {% for i in 1..nestingLevel %}
                                        <span style="position: relative;">
                                    <span style="display: inline-block;
                                        position: absolute;
                                        width: 6px;
                                        height: 20px;
                                        top: 0;
                                        border-left:  2px solid rgba(0, 0, 0, 0.15);
                                        margin-left: {{ i * 10 }}px;"></span>
                                </span>
                                    {% endfor %}
                                {% endif %}
        
                                <div{% if nestingLevel > 0 %} style="padding-left: {{ (nestingLevel + 1) * 10 }}px"{% endif %}>
                                    {{ nestedItem.label|u.wordwrap(80) }}
                                </div>
        
                                {% if nestedItem.payload.options is defined and nestedItem.payload.options|length >= 1 %}
                                    <div>
                                        {% for option in nestedItem.payload.options %}
                                            {{ option.group }}: {{ option.option }}
                                            {% if nestedItem.payload.options|last != option %}
                                                {{ " | " }}
                                            {% endif %}
                                        {% endfor %}
                                    </div>
                                {% endif %}
        
                                {% if nestedItem.payload.features is defined and nestedItem.payload.features|length >= 1 %}
                                    {% set referencePriceFeatures = nestedItem.payload.features|filter(feature => feature.type == 'referencePrice') %}
                                    {% if referencePriceFeatures|length >= 1 %}
                                        {% set referencePriceFeature = referencePriceFeatures|first %}
                                        <div>
                                            {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}
                                            ({{ referencePriceFeature.value.price|currency(currencyIsoCode) }} per {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }})
                                        </div>
                                    {% endif %}
                                {% endif %}
                            </td>
                            <td style="text-align: center">{{ nestedItem.quantity }}</td>
                            <td>{{ nestedItem.unitPrice|currency(currencyIsoCode) }}</td>
                            <td>{{ nestedItem.totalPrice|currency(currencyIsoCode) }}</td>
                        </tr>
        
                        {% if nestedItem.children.count > 0 %}
                            {% set nestingLevel = nestingLevel + 1 %}
                            {% for lineItem in nestedItem.children %}
                                {% set nestedItem = lineItem %}
                                {{ block('lineItem') }}
                            {% endfor %}
                        {% endif %}
                    {% endblock %}
                {% endfor %}
            </table>
        
            {% set delivery = order.deliveries.first %}
        
            {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
            {% set decimals = order.totalRounding.decimals %}
            {% set total = order.price.totalPrice %}
            {% if displayRounded %}
                {% set total = order.price.rawTotal %}
                {% set decimals = order.itemRounding.decimals %}
            {% endif %}
            <p>
                <br>
                <br>
                {% for shippingCost in order.deliveries %}
                    Shipping costs: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
                {% endfor %}
        
                Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
                {% for calculatedTax in order.price.calculatedTaxes %}
                    {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                {% endfor %}
                {% if not displayRounded %}<strong>{% endif %}Total gross: {{ total|currency(currencyIsoCode,decimals=decimals) }}{% if not displayRounded %}</strong>{% endif %}<br>
                {% if displayRounded %}
                    <strong>Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}</strong><br>
                {% endif %}
                <br>
        
                {% if order.transactions is defined and order.transactions is not empty %}
                    <strong>Selected payment type:</strong> {{ order.transactions.first.paymentMethod.translated.name }}<br>
                    {{ order.transactions.first.paymentMethod.translated.description }}<br>
                    <br>
                {% endif %}
        
                {% if delivery %}
                    <strong>Selected shipping type:</strong> {{ delivery.shippingMethod.translated.name }}<br>
                    {{ delivery.shippingMethod.translated.description }}<br>
                    <br>
                {% endif %}
        
                {% set billingAddress = order.addresses.get(order.billingAddressId) %}
                <strong>Billing address:</strong><br>
                {{ billingAddress.company }}<br>
                {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
                {{ billingAddress.street }} <br>
                {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
                {{ billingAddress.country.translated.name }}<br>
                <br>
        
                {% if delivery %}
                    <strong>Shipping address:</strong><br>
                    {{ delivery.shippingOrderAddress.company }}<br>
                    {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
                    {{ delivery.shippingOrderAddress.street }} <br>
                    {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                    {{ delivery.shippingOrderAddress.country.translated.name }}<br>
                    <br>
                {% endif %}
                {% if order.orderCustomer.vatIds %}
                    Your VAT-ID: {{ order.orderCustomer.vatIds|first }}
                    In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br>
                {% endif %}
                <br>
                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
                <br>
                If you have any questions, do not hesitate to contact us.
                <br>
                {% if a11yDocuments is defined and a11yDocuments is not empty %}
                    <br>
                    For better accessibility we also provide an HTML version of the documents here:<br><br>
                    <ul>
                        {% for a11y in a11yDocuments %}
                            {% set documentLink = rawUrl(
                                'frontend.account.order.single.document.a11y',
                                {
                                    documentId: a11y.documentId,
                                    deepLinkCode: a11y.deepLinkCode,
                                    fileType: a11y.fileExtension,
                                },
                                salesChannel.domains|first.url
                            )%}
                            <li><a href="{{ documentLink }}" target="_blank">{{ documentLink }}</a></li>
                        {% endfor %}
                    </ul>
                    For data protection reasons the HTML version requires a login. <br><br>
                    In case of a guest order, you can use your mail address and postal code of the billing address.<br>
                {% endif %}
            </p>
            <br>
        </div>
        MAIL;
    }

    private function getAdminOrderPlacedContentPlainEn(): string
    {
        return <<<MAIL
        {% set currencyIsoCode = order.currency.isoCode %}
        We have received a new pickup order from {{ order.orderDateTime|format_datetime('medium', 'short', locale='en-GB') }}.
        
        Order number: {{ order.orderNumber }}
        
        Please prepare the order for the customer to pick up.
        
        Information on your order:
        
        {% for lineItem in order.lineItems %}
        Pos. {{ loop.index }}
        ---------------------
        {% if lineItem.payload.productNumber is defined %}
        Product number {{ lineItem.payload.productNumber|u.wordwrap(80) }},
        {% endif %}
        {% if nestedItem.cover is defined and nestedItem.cover is not null %}
        Image {{ lineItem.cover.alt }},
        {% endif %}
        Description {{ lineItem.label|u.wordwrap(80) }},
        {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
        {% for option in lineItem.payload.options %}
        {{ option.group }}: {{ option.option }}{{ ", " }}
        {% endfor %}
        {% endif %}
        {% if lineItem.payload.features is defined and lineItem.payload.features|length >= 1 %}
        {% set referencePriceFeatures = lineItem.payload.features|filter(feature => feature.type == 'referencePrice') %}
        {% if referencePriceFeatures|length >= 1 %}
        {% set referencePriceFeature = referencePriceFeatures|first %}
        {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}({{ referencePriceFeature.value.price|currency(currencyIsoCode) }} per {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }}),
        {% endif %}
        {% endif %}
        Quantity {{ lineItem.quantity }},
        Price {{ lineItem.unitPrice|currency(currencyIsoCode) }},
        Total {{ lineItem.totalPrice|currency(currencyIsoCode) }},
        
        {% endfor %}
        {% set delivery = order.deliveries.first %}
        {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
        {% set decimals = order.totalRounding.decimals %}
        {% set total = order.price.totalPrice %}
        {% if displayRounded %}
            {% set total = order.price.rawTotal %}
            {% set decimals = order.itemRounding.decimals %}
        {% endif %}
        {% for shippingCost in order.deliveries %}
        Shipping costs: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}
        Net total: {{ order.amountNet|currency(currencyIsoCode) }}
        {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
        {% endfor %}
        Total gross: {{ total|currency(currencyIsoCode,decimals=decimals) }}
        {% if displayRounded %}
        Rounded total gross: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}
        {% endif %}
        
        {% if order.transactions is defined and order.transactions is not empty %}
        Selected payment type: {{ order.transactions.first.paymentMethod.translated.name }}
        {{ order.transactions.first.paymentMethod.translated.description }}
        {% endif %}
        
        {% if delivery %}
        Selected shipping type: {{ delivery.shippingMethod.translated.name }}
        {{ delivery.shippingMethod.translated.description }}
        {% endif %}
        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        Billing address:
        {{ billingAddress.company }}
        {{ billingAddress.firstName }} {{ billingAddress.lastName }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.translated.name }}
        
        {% if delivery %}
        Shipping address:
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.translated.name }}
        {% endif %}
        
        {% if order.orderCustomer.vatIds %}
        Your VAT-ID: {{ order.orderCustomer.vatIds|first }}
        In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
        
        {% endif %}
        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
        If you have any questions, do not hesitate to contact us.
        
        {% if a11yDocuments is defined and a11yDocuments is not empty %}
        For better accessibility we also provide an HTML version of the documents here:
        
        {% for a11y in a11yDocuments %}
        {% set documentLink = rawUrl(
            'frontend.account.order.single.document.a11y',
            {
                documentId: a11y.documentId,
                'deepLinkCode': a11y.deepLinkCode,
                fileType: a11y.fileExtension,
            },
            salesChannel.domains|first.url
        )%}
        - {{ documentLink }}
        {% endfor %}
        
        For data protection reasons the HTML version requires a login.
        In case of a guest order, you can use your mail address and postal code of the billing address.
        {% endif %}
        MAIL;
    }

    // Admin Order Placed Content - German
    private function getAdminOrderPlacedContentHtmlDe(): string
    {
        return <<<MAIL
        <div style="font-family:arial; font-size:12px;">

            {% set currencyIsoCode = order.currency.isoCode %}
        
            Wir haben am {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-DE') }} eine neue Abholbestellung erhalten.<br>
            <br>
            Bestellnummer: {{ order.orderNumber }}<br>
            <br>
            Bitte bereiten Sie die Bestellung zur Abholung durch den Kunden vor.<br>

            <br>
            <strong>Informationen zu Ihrer Bestellung:</strong><br>
            <br>
        
            <table border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
                <tr>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Produkt-Nr.</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Produktbild</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Bezeichnung</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Menge</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Preis</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Summe</strong></td>
                </tr>
        
                {% for lineItem in order.nestedLineItems %}
                    {% set nestingLevel = 0 %}
                    {% set nestedItem = lineItem %}
                    {% block lineItem %}
                        <tr>
                            <td>{% if nestedItem.payload.productNumber is defined %}{{ nestedItem.payload.productNumber|u.wordwrap(80) }}{% endif %}</td>
                            <td>{% if nestedItem.cover is defined and nestedItem.cover is not null %}<img src="{{ nestedItem.cover.url }}" width="75" height="auto"/>{% endif %}</td>
                            <td>
                                {% if nestingLevel > 0 %}
                                    {% for i in 1..nestingLevel %}
                                        <span style="position: relative;">
                                        <span style="display: inline-block;
                                            position: absolute;
                                            width: 6px;
                                            height: 20px;
                                            top: 0;
                                            border-left:  2px solid rgba(0, 0, 0, 0.15);
                                            margin-left: {{ i * 10 }}px;"></span>
                                    </span>
                                    {% endfor %}
                                {% endif %}
        
                                <div{% if nestingLevel > 0 %} style="padding-left: {{ (nestingLevel + 1) * 10 }}px"{% endif %}>
                                    {{ nestedItem.label|u.wordwrap(80) }}
                                </div>
        
                                {% if nestedItem.payload.options is defined and nestedItem.payload.options|length >= 1 %}
                                    <div>
                                        {% for option in nestedItem.payload.options %}
                                            {{ option.group }}: {{ option.option }}
                                            {% if nestedItem.payload.options|last != option %}
                                                {{ " | " }}
                                            {% endif %}
                                        {% endfor %}
                                    </div>
                                {% endif %}
        
                                {% if nestedItem.payload.features is defined and nestedItem.payload.features|length >= 1 %}
                                    {% set referencePriceFeatures = nestedItem.payload.features|filter(feature => feature.type == 'referencePrice') %}
                                    {% if referencePriceFeatures|length >= 1 %}
                                        {% set referencePriceFeature = referencePriceFeatures|first %}
                                        <div>
                                            {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}
                                            ({{ referencePriceFeature.value.price|currency(currencyIsoCode) }} pro {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }})
                                        </div>
                                    {% endif %}
                                {% endif %}
                            </td>
                            <td style="text-align: center">{{ nestedItem.quantity }}</td>
                            <td>{{ nestedItem.unitPrice|currency(currencyIsoCode) }}</td>
                            <td>{{ nestedItem.totalPrice|currency(currencyIsoCode) }}</td>
                        </tr>
        
                        {% if nestedItem.children.count > 0 %}
                            {% set nestingLevel = nestingLevel + 1 %}
                            {% for lineItem in nestedItem.children %}
                                {% set nestedItem = lineItem %}
                                {{ block('lineItem') }}
                            {% endfor %}
                        {% endif %}
                    {% endblock %}
                {% endfor %}
            </table>
        
            {% set delivery = order.deliveries.first %}
        
            {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
            {% set decimals = order.totalRounding.decimals %}
            {% set total = order.price.totalPrice %}
            {% if displayRounded %}
                {% set total = order.price.rawTotal %}
                {% set decimals = order.itemRounding.decimals %}
            {% endif %}
            <p>
                <br>
                <br>
                {% for shippingCost in order.deliveries %}
                    Versandkosten: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
                {% endfor %}
                Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}<br>
                {% for calculatedTax in order.price.calculatedTaxes %}
                    {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                {% endfor %}
                {% if not displayRounded %}<strong>{% endif %}Gesamtkosten Brutto: {{ total|currency(currencyIsoCode,decimals=decimals) }}{% if not displayRounded %}</strong>{% endif %}<br>
                {% if displayRounded %}
                    <strong>Gesamtkosten Brutto gerundet: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}</strong><br>
                {% endif %}
                <br>
        
                {% if order.transactions is defined and order.transactions is not empty %}
                    <strong>Gewählte Zahlungsart:</strong> {{ order.transactions.first.paymentMethod.translated.name }}<br>
                    {{ order.transactions.first.paymentMethod.translated.description }}<br>
                    <br>
                {% endif %}
        
                {% if delivery %}
                    <strong>Gewählte Versandart:</strong> {{ delivery.shippingMethod.translated.name }}<br>
                    {{ delivery.shippingMethod.translated.description }}<br>
                    <br>
                {% endif %}
        
                {% set billingAddress = order.addresses.get(order.billingAddressId) %}
                <strong>Rechnungsadresse:</strong><br>
                {{ billingAddress.company }}<br>
                {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
                {{ billingAddress.street }} <br>
                {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
                {{ billingAddress.country.translated.name }}<br>
                <br>
        
                {% if delivery %}
                    <strong>Lieferadresse:</strong><br>
                    {{ delivery.shippingOrderAddress.company }}<br>
                    {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
                    {{ delivery.shippingOrderAddress.street }} <br>
                    {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                    {{ delivery.shippingOrderAddress.country.translated.name }}<br>
                    <br>
                {% endif %}
                {% if order.orderCustomer.vatIds %}
                    Ihre Umsatzsteuer-ID: {{ order.orderCustomer.vatIds|first }}
                    Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
                    bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit. <br>
                {% endif %}
                <br>
                Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im  Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
                <br>
                Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
                <br>
                {% if a11yDocuments is defined and a11yDocuments is not empty %}
                    <br>
                    Folgend stellen wir barrierefreie Dokumente als HTML-Version zur Verfügung:<br><br>
                    <ul>
                        {% for a11y in a11yDocuments %}
                            {% set documentLink = rawUrl(
                                'frontend.account.order.single.document.a11y',
                                {
                                    documentId: a11y.documentId,
                                    deepLinkCode: a11y.deepLinkCode,
                                    fileType: a11y.fileExtension,
                                },
                                salesChannel.domains|first.url
                            )%}
                            <li><a href="{{ documentLink }}" target="_blank">{{ documentLink }}</a></li>
                        {% endfor %}
                    </ul>
                    Aus Datenschutzgründen ist für die HTML-Version ein Login erforderlich.<br><br>
                    Im Falle einer Gastbestellung können Sie Ihre Postanschrift und die Postleitzahl der Rechnungsanschrift verwenden.<br>
                {% endif %}
            </p>
            <br>
        </div>
        MAIL;
    }

    private function getAdminOrderPlacedContentPlainDe(): string
    {
        return <<<MAIL
        {% set currencyIsoCode = order.currency.isoCode %}
        Wir haben am {{ order.orderDateTime|format_datetime('medium', 'short', locale='de-DE') }} eine neue Abholbestellung erhalten.<br>
        
        Bestellnummer: {{ order.orderNumber }}
        
        Bitte bereiten Sie die Bestellung zur Abholung durch den Kunden vor.
        
        Informationen zu Ihrer Bestellung:
        
        {% for lineItem in order.lineItems %}
        Pos. {{ loop.index }}
        ---------------------
        {% if lineItem.payload.productNumber is defined %}
        Artikel-Nr. {{ lineItem.payload.productNumber|u.wordwrap(80) }},
        {% endif %}
        {% if nestedItem.cover is defined and nestedItem.cover is not null %}
        Produktbild {{ lineItem.cover.alt }},
        {% endif %}
        Beschreibung {{ lineItem.label|u.wordwrap(80) }},
        {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
        {% for option in lineItem.payload.options %}
        {{ option.group }}: {{ option.option }}{{ ", " }}
        {% endfor %}
        {% endif %}
        {% if lineItem.payload.features is defined and lineItem.payload.features|length >= 1 %}
        {% set referencePriceFeatures = lineItem.payload.features|filter(feature => feature.type == 'referencePrice') %}
        {% if referencePriceFeatures|length >= 1 %}
        {% set referencePriceFeature = referencePriceFeatures|first %}
        {{ referencePriceFeature.value.purchaseUnit }} {{ referencePriceFeature.value.unitName }}({{ referencePriceFeature.value.price|currency(currencyIsoCode) }} pro {{ referencePriceFeature.value.referenceUnit }} {{ referencePriceFeature.value.unitName }}),
        {% endif %}
        {% endif %}
        Menge {{ lineItem.quantity }},
        Preis {{ lineItem.unitPrice|currency(currencyIsoCode) }},
        Summe {{ lineItem.totalPrice|currency(currencyIsoCode) }},
        
        {% endfor %}
        {% set delivery = order.deliveries.first %}
        {% set displayRounded = order.totalRounding.interval != 0.01 or order.totalRounding.decimals != order.itemRounding.decimals %}
        {% set decimals = order.totalRounding.decimals %}
        {% set total = order.price.totalPrice %}
        {% if displayRounded %}
            {% set total = order.price.rawTotal %}
            {% set decimals = order.itemRounding.decimals %}
        {% endif %}
        {% for shippingCost in order.deliveries %}
        Versandkosten: {{ shippingCost.shippingCosts.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}
        Gesamtkosten Netto: {{ order.amountNet|currency(currencyIsoCode) }}
        {% for calculatedTax in order.price.calculatedTaxes %}
        {% if order.taxStatus is same as('net') %}zzgl.{% else %}inkl.{% endif %} {{ calculatedTax.taxRate }}% MwSt. {{ calculatedTax.tax|currency(currencyIsoCode) }}
        {% endfor %}
        Gesamtkosten Brutto: {{ total|currency(currencyIsoCode,decimals=decimals) }}
        {% if displayRounded %}
        Gesamtkosten Brutto gerundet: {{ order.price.totalPrice|currency(currencyIsoCode,decimals=order.totalRounding.decimals) }}
        {% endif %}
        
        {% if order.transactions is defined and order.transactions is not empty %}
        Gewählte Zahlungsart: {{ order.transactions.first.paymentMethod.translated.name }}
        {{ order.transactions.first.paymentMethod.translated.description }}
        {% endif %}
        
        {% if delivery %}
        Gewählte Versandart: {{ delivery.shippingMethod.translated.name }}
        {{ delivery.shippingMethod.translated.description }}
        {% endif %}
        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        Rechnungsadresse:
        {{ billingAddress.company }}
        {{ billingAddress.firstName }} {{ billingAddress.lastName }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.translated.name }}
        
        {% if delivery %}
        Lieferadresse:
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.translated.name }}
        {% endif %}
        
        {% if order.orderCustomer.vatIds %}
        Ihre Umsatzsteuer-ID: {{ order.orderCustomer.vatIds|first }}
        Bei erfolgreicher Prüfung und sofern Sie aus dem EU-Ausland
        bestellen, erhalten Sie Ihre Ware umsatzsteuerbefreit.
        
        {% endif %}
        Den aktuellen Status Ihrer Bestellung können Sie auch jederzeit auf unserer Webseite im Bereich "Mein Konto" - "Meine Bestellungen" abrufen: {{ rawUrl('frontend.account.order.single.page', { 'deepLinkCode': order.deepLinkCode }, salesChannel.domains|first.url) }}
        Für Rückfragen stehen wir Ihnen jederzeit gerne zur Verfügung.
        
        {% if a11yDocuments is defined and a11yDocuments is not empty %}
        Folgend stellen wir barrierefreie Dokumente als HTML-Version zur Verfügung:
        
        {% for a11y in a11yDocuments %}
        {% set documentLink = rawUrl(
            'frontend.account.order.single.document.a11y',
            {
                documentId: a11y.documentId,
                deepLinkCode: a11y.deepLinkCode,
                fileType: a11y.fileExtension,
            },
            salesChannel.domains|first.url
        )%}
        - {{ documentLink }}
        {% endfor %}
        
        Aus Datenschutzgründen ist für die HTML-Version ein Login erforderlich.
        Im Falle einer Gastbestellung können Sie Ihre Postanschrift und die Postleitzahl der Rechnungsanschrift verwenden.
        {% endif %}
        MAIL;
    }
}