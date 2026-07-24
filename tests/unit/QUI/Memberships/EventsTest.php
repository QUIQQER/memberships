<?php

namespace QUI\Memberships;

use DateTime;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Contracts\Contract;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Products\Field\Field as ProductField;
use QUI\ERP\Products\Product\Product;
use QUI\Package\Package;
use QUI\Users\User;

class EventsTest extends TestCase
{
    public function testPackageSetupIgnoresOtherPackages(): void
    {
        $Package = $this->getMockBuilder(Package::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getName'])
            ->getMock();
        $Package->method('getName')->willReturn('quiqqer/example');

        Events::onPackageSetup($Package);
        self::assertTrue(true);
    }

    public function testPackageSetupKeepsExistingProductConfiguration(): void
    {
        $MembershipField = Handler::getProductMembershipField();
        $MembershipFlagField = Handler::getProductMembershipFlagField();
        $Category = Handler::getProductCategory();

        self::assertNotFalse($MembershipField);
        self::assertNotFalse($MembershipFlagField);
        self::assertNotFalse($Category);

        Events::onPackageSetup(QUI::getPackage('quiqqer/memberships'));

        self::assertSame(
            $MembershipField->getId(),
            Handler::getProductMembershipField()->getId()
        );
        self::assertSame(
            $MembershipFlagField->getId(),
            Handler::getProductMembershipFlagField()->getId()
        );
        self::assertSame(
            $Category->getId(),
            Handler::getProductCategory()->getId()
        );
    }

    public function testUserSaveStopsWithoutDefaultMembership(): void
    {
        $Config = Handler::getConfig();
        $previousDefaultMembershipId = $Config->get('memberships', 'defaultMembershipId');
        $Config->set('memberships', 'defaultMembershipId', 0);

        try {
            $User = $this->getMockBuilder(User::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getUUID'])
                ->getMock();
            $User->expects(self::once())
                ->method('getUUID')
                ->willReturn('00000000-0000-4000-8000-000000000001');

            Events::onUserSave($User);
        } finally {
            $Config->set(
                'memberships',
                'defaultMembershipId',
                $previousDefaultMembershipId
            );
        }
    }

    public function testUserEventsStopForMissingUuid(): void
    {
        $User = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUUID'])
            ->getMock();
        $User->method('getUUID')->willReturn('');

        Events::onUserSave($User);
        Events::onUserDelete($User);

        self::assertTrue(true);
    }

    public function testProductEventsStopWithoutConfiguredMembershipFields(): void
    {
        $this->withoutProductFields(function (): void {
            $Product = $this->getMockBuilder(Product::class)
                ->disableOriginalConstructor()
                ->getMock();
            $Field = $this->getMockBuilder(ProductField::class)
                ->disableOriginalConstructor()
                ->getMock();

            Events::onQuiqqerProductsProductDelete($Product);
            Events::onQuiqqerProductsFieldDeleteBefore($Field);
        });

        self::assertTrue(true);
    }

    public function testProductDeleteIgnoresProductsWithoutUsableMembershipAssignment(): void
    {
        $MembershipField = Handler::getProductMembershipField();

        self::assertNotFalse($MembershipField);

        foreach ([null, [], 0] as $membershipId) {
            $Product = $this->getMockBuilder(Product::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getFieldValue', 'getId'])
                ->getMock();
            $Product->expects(self::once())
                ->method('getFieldValue')
                ->with($MembershipField->getId())
                ->willReturn($membershipId);
            $Product->expects(self::never())->method('getId');

            Events::onQuiqqerProductsProductDelete($Product);
        }
    }

    public function testConfiguredMembershipProductFieldsCannotBeDeleted(): void
    {
        $fields = [
            Handler::getProductMembershipField(),
            Handler::getProductMembershipFlagField()
        ];
        $caught = 0;

        foreach ($fields as $Field) {
            self::assertInstanceOf(ProductField::class, $Field);

            try {
                Events::onQuiqqerProductsFieldDeleteBefore($Field);
            } catch (QUI\Database\Exception) {
                $caught++;
            }
        }

        self::assertSame(2, $caught);
    }

    public function testOrderEventStopsWithoutConfiguredMembershipField(): void
    {
        $this->withoutProductFields(function (): void {
            $Order = $this->getMockBuilder(AbstractOrder::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getPrefixedId'])
                ->getMockForAbstractClass();
            $Order->method('getPrefixedId')->willReturn('PHPUNIT-ORDER');

            Events::onQuiqqerOrderSuccessful($Order);
        });

        self::assertTrue(true);
    }

    public function testContractEventsStopWhenLinkingIsDisabled(): void
    {
        $Config = Handler::getConfig();
        $previousLinkSetting = $Config->get('membershipusers', 'linkWithContracts');
        $Config->set('membershipusers', 'linkWithContracts', 0);

        try {
            $Contract = $this->getMockBuilder(Contract::class)
                ->disableOriginalConstructor()
                ->getMock();
            $Order = $this->getMockBuilder(AbstractOrder::class)
                ->disableOriginalConstructor()
                ->getMockForAbstractClass();
            $EndDate = new DateTime('2024-01-31 23:59:59');
            $NewEndDate = new DateTime('2024-02-29 23:59:59');

            Events::onQuiqqerContractsExtend($Contract, $EndDate, $NewEndDate);
            Events::onQuiqqerContractsCreateFromOrder($Contract, $Order);
            Events::onQuiqqerContractsCancel($Contract);
        } finally {
            $Config->set(
                'membershipusers',
                'linkWithContracts',
                $previousLinkSetting
            );
        }

        self::assertTrue(true);
    }

    public function testContractDeleteClearsUnknownContractIdWithoutSideEffects(): void
    {
        $Contract = $this->getMockBuilder(Contract::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCleanId'])
            ->getMock();
        $Contract->method('getCleanId')->willReturn(2147483647);

        Events::onQuiqqerContractsDelete($Contract);
        self::assertTrue(true);
    }

    public function testUnverifiedEventIgnoresUnknownMembershipUser(): void
    {
        Events::onQuiqqerVerificationDeleteUnverified(2147483647);
        self::assertTrue(true);
    }

    /**
     * @param callable(): void $callback
     */
    private function withoutProductFields(callable $callback): void
    {
        $Config = Handler::getConfig();
        $previousMembershipFieldId = $Config->get('products', 'membershipFieldId');
        $previousMembershipFlagFieldId = $Config->get('products', 'membershipFlagFieldId');
        $Config->set('products', 'membershipFieldId', 0);
        $Config->set('products', 'membershipFlagFieldId', 0);

        try {
            $callback();
        } finally {
            $Config->set('products', 'membershipFieldId', $previousMembershipFieldId);
            $Config->set(
                'products',
                'membershipFlagFieldId',
                $previousMembershipFlagFieldId
            );
        }
    }
}
