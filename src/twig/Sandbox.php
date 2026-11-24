<?php

namespace webdna\craftemailcontenteditor\twig;

use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderStatus;
use craft\elements\Address;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;

/**
 * Renders the Twig that control panel users write into emails (subjects and content).
 *
 * The environment loads nothing from Craft's Twig setup (no globals, no `craft` variable, no Craft filters
 * or functions) and can't load template files. Everything is rendered in Twig's sandbox against an allowlist,
 * so PHP functions can't be called (e.g. `map('system')`), constants and environment variables can't be read,
 * user accounts can't be read, and objects can't be looped over to dump their data.
 *
 * Sites can extend the allowlist with the `sandbox` setting in `config/email-content-editor.php`.
 */
class Sandbox
{
    public const DEFAULT_TAGS = ['apply', 'for', 'if', 'set', 'verbatim'];

    /**
     * Left out on purpose: `raw` (and the `autoescape` tag) would let text injected into an email produce
     * HTML, e.g. phishing links; `format` and `split` (and the `range` function) can exhaust memory or CPU;
     * `column`, `json_encode` and `batch` read objects' internals.
     */
    public const DEFAULT_FILTERS = [
        'abs', 'capitalize', 'date', 'date_modify', 'default', 'e', 'escape', 'filter', 'find', 'first', 'join',
        'keys', 'last', 'length', 'lower', 'map', 'merge', 'nl2br', 'number_format', 'reduce', 'replace',
        'reverse', 'round', 'slice', 'sort', 'striptags', 'title', 'trim', 'upper', 'url_encode',
    ];

    public const DEFAULT_FUNCTIONS = ['date'];

    /**
     * Properties that can be read on objects passed to emails, beyond those Craft already marks as safe
     * (such as `entry.title`, `entry.url` and custom fields). Commerce marks nothing as safe, so its classes are
     * listed here. Each property's getter (e.g. `getTotalPrice()`) is allowed too.
     */
    public const DEFAULT_PROPERTIES = [
        Address::class => [
            'fullName', 'firstName', 'lastName', 'organization', 'addressLine1', 'addressLine2', 'addressLine3',
            'locality', 'dependentLocality', 'administrativeArea', 'postalCode', 'sortingCode', 'countryCode',
        ],
        Order::class => [
            'number', 'reference', 'shortNumber', 'email', 'couponCode', 'isCompleted', 'dateOrdered', 'datePaid',
            'currency', 'paymentCurrency', 'message', 'orderLanguage', 'shippingMethodName', 'totalQty',
            'totalWeight', 'paidStatus', 'lineItems', 'billingAddress', 'shippingAddress', 'orderStatus',
            'itemSubtotal', 'itemTotal', 'totalDiscount', 'totalShippingCost', 'totalTax', 'totalTaxIncluded',
            'total', 'totalPrice', 'totalPaid', 'outstandingBalance',
            'itemSubtotalAsCurrency', 'itemTotalAsCurrency', 'totalDiscountAsCurrency',
            'totalShippingCostAsCurrency', 'totalTaxAsCurrency', 'totalTaxIncludedAsCurrency', 'totalAsCurrency',
            'totalPriceAsCurrency', 'totalPaidAsCurrency', 'outstandingBalanceAsCurrency',
        ],
        LineItem::class => [
            'description', 'sku', 'qty', 'options', 'note', 'onSale',
            'price', 'salePrice', 'promotionalPrice', 'subtotal', 'total', 'discount', 'tax', 'taxIncluded',
            'shippingCost',
            'priceAsCurrency', 'salePriceAsCurrency', 'promotionalPriceAsCurrency', 'subtotalAsCurrency',
            'totalAsCurrency', 'discountAsCurrency', 'taxAsCurrency', 'shippingCostAsCurrency',
        ],
        OrderStatus::class => ['name', 'handle', 'color', 'description'],
        OrderHistory::class => ['message', 'dateCreated', 'newStatus', 'prevStatus'],
    ];

    private Environment $twig;

    /**
     * @param array $config Additions to the allowlist: `allowedTags`, `allowedFilters` and `allowedFunctions`
     * (lists of names), and `allowedProperties` and `allowedMethods` (class name => list of names).
     */
    public function __construct(array $config = [], string $charset = 'UTF-8')
    {
        $properties = self::DEFAULT_PROPERTIES;
        foreach ($config['allowedProperties'] ?? [] as $class => $names) {
            $properties[$class] = array_merge($properties[$class] ?? [], $names);
        }

        $methods = [];
        foreach ($properties as $class => $names) {
            $methods[$class] = array_map(fn(string $name) => 'get' . ucfirst($name), $names);
        }
        foreach ($config['allowedMethods'] ?? [] as $class => $names) {
            $methods[$class] = array_merge($methods[$class] ?? [], $names);
        }

        $policy = new SecurityPolicy(
            array_merge(self::DEFAULT_TAGS, $config['allowedTags'] ?? []),
            array_merge(self::DEFAULT_FILTERS, $config['allowedFilters'] ?? []),
            array_merge(self::DEFAULT_FUNCTIONS, $config['allowedFunctions'] ?? []),
            $methods,
            $properties,
        );

        $this->twig = new Environment(new ArrayLoader(), [
            // Each email's content is different (names, order numbers), so caching would write a new compiled
            // PHP file for every email sent.
            'cache' => false,
            'charset' => $charset,
            'autoescape' => 'html',
        ]);
        $this->twig->addExtension(new SandboxExtension($policy, true));
        $this->twig->addNodeVisitor(new IterationGuard());
    }

    /**
     * Renders a Twig string in the sandbox.
     *
     * @throws \Twig\Error\Error if the template is invalid or uses something the sandbox doesn't allow
     */
    public function render(string $template, array $variables = []): string
    {
        return $this->twig->createTemplate($template)->render($variables);
    }
}
