<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTagRule;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Symfony\Component\Validator\Constraints\NotBlank;

class LineItemTagRuleTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $conditionRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingIdentifiers(): void
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemTagRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithEmptyIdentifiers(): void
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemTagRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'identifiers' => [],
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithStringIdentifiers(): void
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemTagRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'identifiers' => '0915d54fbf80423c917c61ad5a391b48',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type array.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidArrayIdentifiers(): void
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemTagRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'identifiers' => [true, 3, '1234abcd', '0915d54fbf80423c917c61ad5a391b48'],
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(3, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/identifiers', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The value "1" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('The value "3" is not a valid uuid.', $exception->getViolations()->get(1)->getMessage());
                static::assertSame('The value "1234abcd" is not a valid uuid.', $exception->getViolations()->get(2)->getMessage());
            }
        }
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::uuid4()->getHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LineItemTagRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => ['0915d54fbf80423c917c61ad5a391b48', '6f7a6b89579149b5b687853271608949'],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testNoMatchWithoutTags(): void
    {
        $rule = (new LineItemTagRule())->assign(['identifiers' => [Uuid::uuid4()->getHex()]]);
        $cart = new Cart('test', 'test');
        $cart->add(new LineItem('key', 'product'));
        $cart->add(new LineItem('key2', 'product'));

        $cartRuleScope = new CartRuleScope($cart, $this->createMock(CheckoutContext::class));

        $match = $rule->match($cartRuleScope);
        static::assertFalse($match->matches());
        static::assertSame(['Line items not in cart'], $match->getMessages());
    }

    public function testMatchUnequalsTags(): void
    {
        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_NEQ, 'identifiers' => [Uuid::uuid4()->getHex()]]);
        $cart = new Cart('test', 'test');
        $cart->add(new LineItem('key', 'product'));
        $cart->add(new LineItem('key2', 'product'));

        $cartRuleScope = new CartRuleScope($cart, $this->createMock(CheckoutContext::class));

        $match = $rule->match($cartRuleScope);
        static::assertTrue($match->matches());
    }

    public function testMatchWithMatchingTags(): void
    {
        $tagIds = [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_EQ, 'identifiers' => $tagIds]);
        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('key', 'product'))->replacePayload(['tags' => $tagIds]));
        $cart->add(new LineItem('key2', 'product'));

        $cartRuleScope = new CartRuleScope($cart, $this->createMock(CheckoutContext::class));

        $match = $rule->match($cartRuleScope);
        static::assertTrue($match->matches());
    }

    public function testMatchWithPartialMatchingTags(): void
    {
        $tagIds = [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_EQ, 'identifiers' => $tagIds]);
        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('key', 'product'))->replacePayload(['tags' => [$tagIds[0]]]));
        $cart->add((new LineItem('key2', 'product'))->replacePayload(['tags' => [Uuid::uuid4()->getHex()]]));

        $cartRuleScope = new CartRuleScope($cart, $this->createMock(CheckoutContext::class));

        $match = $rule->match($cartRuleScope);
        static::assertTrue($match->matches());
    }

    public function testNoMatchWithPartialMatchingUnequalOperatorTags(): void
    {
        $tagIds = [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()];

        $rule = (new LineItemTagRule())->assign(['operator' => Rule::OPERATOR_NEQ, 'identifiers' => $tagIds]);
        $cart = new Cart('test', 'test');
        $cart->add((new LineItem('key', 'product'))->replacePayload(['tags' => [$tagIds[0]]]));
        $cart->add((new LineItem('key2', 'product'))->replacePayload(['tags' => [Uuid::uuid4()->getHex()]]));

        $cartRuleScope = new CartRuleScope($cart, $this->createMock(CheckoutContext::class));

        $match = $rule->match($cartRuleScope);
        static::assertFalse($match->matches());
        static::assertSame(['Line items in cart'], $match->getMessages());
    }
}