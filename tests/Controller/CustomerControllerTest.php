<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Configuration\SystemConfiguration;
use App\Entity\Customer;
use App\Entity\CustomerComment;
use App\Entity\CustomerMeta;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Tests\DataFixtures\CustomerFixtures;
use App\Tests\DataFixtures\ProjectFixtures;
use App\Tests\DataFixtures\TeamFixtures;
use App\Tests\DataFixtures\TimesheetFixtures;
use App\Tests\Mocks\CustomerTestMetaFieldSubscriberMock;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

/**
 * @group integration
 */
class CustomerControllerTest extends ControllerBaseTest
{
    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/admin/customer/');
    }

    public function testIsSecureForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/customer/');
    }

    public function testIndexAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/customer/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'search' => '#',
            'visibility' => '#',
            'download toolbar-action' => $this->createUrl('/admin/customer/export'),
            'help' => 'https://www.kimai.org/documentation/customer.html'
        ]);
    }

    public function testIndexActionAsSuperAdmin()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'search' => '#',
            'visibility' => '#',
            'download toolbar-action' => $this->createUrl('/admin/customer/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/customer/create'),
            'settings modal-ajax-form' => $this->createUrl('/admin/system-config/edit/customer'),
            'help' => 'https://www.kimai.org/documentation/customer.html'
        ]);
    }

    public function testIndexActionWithSearchTermQuery()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new CustomerFixtures();
        $fixture->setAmount(5);
        $fixture->setCallback(function (Customer $customer) {
            $customer->setVisible(true);
            $customer->setComment('I am a foobar with tralalalala some more content');
            $customer->setMetaField((new CustomerMeta())->setName('location')->setValue('homeoffice'));
            $customer->setMetaField((new CustomerMeta())->setName('feature')->setValue('timetracking'));
        });
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/customer/');

        $this->assertPageActions($client, [
            'search' => '#',
            'visibility' => '#',
            'download toolbar-action' => $this->createUrl('/admin/customer/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/customer/create'),
            'help' => 'https://www.kimai.org/documentation/customer.html'
        ]);

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo',
            'visibility' => 1,
            'pageSize' => 50,
            'page' => 1,
        ]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_customer_admin', 5);
    }

    public function testExportIsSecureForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/customer/export');
    }

    public function testExportAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/customer/export');
        $this->assertExcelExportResponse($client, 'kimai-customers_');
    }

    public function testExportActionWithSearchTermQuery()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/admin/customer/');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $form->getFormNode()->setAttribute('action', $this->createUrl('/admin/customer/export'));
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo',
            'visibility' => 1,
            'pageSize' => 50,
            'page' => 1,
        ]);

        $this->assertExcelExportResponse($client, 'kimai-customers_');
    }

    public function testDetailsAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        self::assertHasProgressbar($client);

        $node = $client->getCrawler()->filter('div.box#customer_details_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.box#project_list_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.box#time_budget_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.box#budget_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.box#team_listing_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.box#comments_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.box#team_listing_box a.btn.btn-default');
        self::assertEquals(2, $node->count());
        $node = $client->getCrawler()->filter('div.box#customer_rates_box');
        self::assertEquals(1, $node->count());
    }

    public function testAddRateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/rate');
        $form = $client->getCrawler()->filter('form[name=customer_rate_form]')->form();
        $client->submit($form, [
            'customer_rate_form' => [
                'user' => null,
                'rate' => 123.45,
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.box#customer_rates_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.box#customer_rates_box table.dataTable tbody tr:not(.summary)');
        self::assertEquals(1, $node->count());
        self::assertStringContainsString('123.45', $node->text(null, true));
    }

    public function testAddCommentAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $form = $client->getCrawler()->filter('form[name=customer_comment_form]')->form();
        $client->submit($form, [
            'customer_comment_form' => [
                'message' => 'A beautiful and short comment **with some** markdown formatting',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.box#comments_box .direct-chat-text');
        self::assertStringContainsString('A beautiful and short comment **with some** markdown formatting', $node->html());

        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $configService = static::$kernel->getContainer()->get(SystemConfiguration::class);
        $configService->offsetSet('timesheet.markdown_content', true);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $node = $client->getCrawler()->filter('div.box#comments_box .direct-chat-text');
        self::assertStringContainsString('<p>A beautiful and short comment <strong>with some</strong> markdown formatting</p>', $node->html());
    }

    public function testDeleteCommentAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $form = $client->getCrawler()->filter('form[name=customer_comment_form]')->form();
        $client->submit($form, [
            'customer_comment_form' => [
                'message' => 'Blah foo bar',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();

        $token = self::$container->get('security.csrf.token_manager')->getToken('customer.delete_comment');

        $node = $client->getCrawler()->filter('div.box#comments_box .direct-chat-msg');
        self::assertStringContainsString('Blah foo bar', $node->html());
        $node = $client->getCrawler()->filter('div.box#comments_box .box-body a.confirmation-link');
        self::assertStringEndsWith('/comment_delete/' . $token, $node->attr('href'));

        $comments = $this->getEntityManager()->getRepository(CustomerComment::class)->findAll();
        $id = $comments[0]->getId();

        $this->request($client, '/admin/customer/' . $id . '/comment_delete/' . $token);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.box#comments_box .box-body');
        self::assertStringContainsString('There were no comments posted yet', $node->html());
    }

    public function testDeleteCommentActionWithoutToken()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $form = $client->getCrawler()->filter('form[name=customer_comment_form]')->form();
        $client->submit($form, [
            'customer_comment_form' => [
                'message' => 'Blah foo bar',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();

        $comments = $this->getEntityManager()->getRepository(CustomerComment::class)->findAll();
        $id = $comments[0]->getId();

        $this->request($client, '/admin/customer/' . $id . '/comment_delete');

        $this->assertRouteNotFound($client);
    }

    public function testPinCommentAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $form = $client->getCrawler()->filter('form[name=customer_comment_form]')->form();
        $client->submit($form, [
            'customer_comment_form' => [
                'message' => 'Blah foo bar',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.box#comments_box .direct-chat-text');
        self::assertStringContainsString('Blah foo bar', $node->html());
        $node = $client->getCrawler()->filter('div.box#comments_box .direct-chat-text a.btn.active');
        self::assertEquals(0, $node->count());

        $comments = $this->getEntityManager()->getRepository(CustomerComment::class)->findAll();
        $id = $comments[0]->getId();

        $token = self::$container->get('security.csrf.token_manager')->getToken('customer.pin_comment');

        $this->request($client, '/admin/customer/' . $id . '/comment_pin/' . $token);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.box#comments_box .box-body a.btn.active');
        $token2 = self::$container->get('security.csrf.token_manager')->getToken('customer.pin_comment');
        self::assertEquals(1, $node->count());
        self::assertEquals($this->createUrl('/admin/customer/' . $id . '/comment_pin/' . $token2), $node->attr('href'));
        self::assertNotEquals($token, $token2);
    }

    public function testCreateDefaultTeamAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $node = $client->getCrawler()->filter('div.box#team_listing_box .box-body');
        self::assertStringContainsString('Visible to everyone, as no team was assigned yet.', $node->text(null, true));

        $this->request($client, '/admin/customer/1/create_team');
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.box#team_listing_box .box-title');
        self::assertStringContainsString('Only visible to the following teams and all admins.', $node->text(null, true));
        $node = $client->getCrawler()->filter('div.box#team_listing_box .box-body table tbody tr');
        self::assertEquals(1, $node->count());

        // creating the default team a second time fails, as the name already exists
        $this->request($client, '/admin/customer/1/create_team');
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $this->assertHasFlashError($client, 'Changes could not be saved: Team already existing');
    }

    public function testProjectsAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/projects/1');
        $node = $client->getCrawler()->filter('div.box#project_list_box .box-body table tbody tr');
        self::assertEquals(1, $node->count());

        /** @var EntityManager $em */
        $em = $this->getEntityManager();
        $customer = $em->getRepository(Customer::class)->find(1);

        $fixture = new ProjectFixtures();
        $fixture->setAmount(9); // to trigger a second page (every third activity is hidden)
        $fixture->setCustomers([$customer]);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/customer/1/projects/1');

        $node = $client->getCrawler()->filter('div.box#project_list_box .box-tools ul.pagination li');
        self::assertEquals(4, $node->count());

        $node = $client->getCrawler()->filter('div.box#project_list_box .box-body table tbody tr');
        self::assertEquals(5, $node->count());
    }

    public function testCreateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/create');
        $form = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();

        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $defaults = $container->getParameter('kimai.defaults')['customer'];
        $this->assertNull($defaults['timezone']);

        $editForm = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        $this->assertEquals($defaults['country'], $editForm->get('customer_edit_form[country]')->getValue());
        $this->assertEquals($defaults['currency'], $editForm->get('customer_edit_form[currency]')->getValue());
        $this->assertEquals(date_default_timezone_get(), $editForm->get('customer_edit_form[timezone]')->getValue());

        $client->submit($form, [
            'customer_edit_form' => [
                'name' => 'Test Customer',
            ]
        ]);
        $this->assertIsRedirect($client, '/details');
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);
    }

    public function testCreateActionShowsMetaFields()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        static::$kernel->getContainer()->get('event_dispatcher')->addSubscriber(new CustomerTestMetaFieldSubscriberMock());
        $this->assertAccessIsGranted($client, '/admin/customer/create');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        $this->assertTrue($form->has('customer_edit_form[metaFields][metatestmock][value]'));
        $this->assertTrue($form->has('customer_edit_form[metaFields][foobar][value]'));
        $this->assertFalse($form->has('customer_edit_form[metaFields][0][value]'));
    }

    public function testEditAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/edit');
        $form = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        $this->assertEquals('Test', $form->get('customer_edit_form[name]')->getValue());
        $client->submit($form, [
            'customer_edit_form' => [
                'name' => 'Test Customer 2'
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $this->request($client, '/admin/customer/1/edit');
        $editForm = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        $this->assertEquals('Test Customer 2', $editForm->get('customer_edit_form[name]')->getValue());
    }

    public function testTeamPermissionAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        /** @var Customer $customer */
        $customer = $em->getRepository(Customer::class)->find(1);
        self::assertEquals(0, $customer->getTeams()->count());

        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $fixture->setAddCustomer(false);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/customer/1/permissions');
        $form = $client->getCrawler()->filter('form[name=customer_team_permission_form]')->form();
        /** @var ChoiceFormField $team1 */
        $team1 = $form->get('customer_team_permission_form[teams][0]');
        $team1->tick();
        /** @var ChoiceFormField $team2 */
        $team2 = $form->get('customer_team_permission_form[teams][1]');
        $team2->tick();

        $client->submit($form);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));

        /** @var Customer $customer */
        $customer = $em->getRepository(Customer::class)->find(1);
        self::assertEquals(2, $customer->getTeams()->count());
    }

    public function testDeleteAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new CustomerFixtures();
        $fixture->setAmount(1);
        $customers = $this->importFixture($fixture);
        $customer = $customers[0];
        $id = $customer->getId();

        $this->request($client, '/admin/customer/' . $id . '/edit');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->request($client, '/admin/customer/' . $id . '/delete');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        $this->assertStringEndsWith($this->createUrl('/admin/customer/' . $id . '/delete'), $form->getUri());
        $client->submit($form);

        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $this->request($client, '/admin/customer/' . $id . '/edit');
        $this->assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithTimesheetEntries()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $em = $this->getEntityManager();
        $fixture = new TimesheetFixtures();
        $fixture->setUser($this->getUserByRole(User::ROLE_USER));
        $fixture->setAmount(10);
        $this->importFixture($fixture);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        $this->assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            $this->assertEquals(1, $entry->getActivity()->getId());
        }

        $this->request($client, '/admin/customer/1/delete');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        $this->assertStringEndsWith($this->createUrl('/admin/customer/1/delete'), $form->getUri());
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);
        $this->assertHasNoEntriesWithFilter($client);

        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        $this->assertEquals(0, \count($timesheets));

        $this->request($client, '/admin/customer/1/edit');
        $this->assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithTimesheetEntriesAndReplacement()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $em = $this->getEntityManager();
        $fixture = new TimesheetFixtures();
        $fixture->setUser($this->getUserByRole(User::ROLE_USER));
        $fixture->setAmount(10);
        $this->importFixture($fixture);
        $fixture = new CustomerFixtures();
        $fixture->setAmount(1)->setIsVisible(true);
        $customers = $this->importFixture($fixture);
        $customer = $customers[0];
        $id = $customer->getId();

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        $this->assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            $this->assertEquals(1, $entry->getProject()->getCustomer()->getId());
        }

        $this->request($client, '/admin/customer/1/delete');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        $this->assertStringEndsWith($this->createUrl('/admin/customer/1/delete'), $form->getUri());
        $client->submit($form, [
            'form' => [
                'customer' => $id
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/'));
        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        $this->assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            $this->assertEquals($id, $entry->getProject()->getCustomer()->getId());
        }

        $this->request($client, '/admin/customer/1/edit');
        $this->assertFalse($client->getResponse()->isSuccessful());
    }

    /**
     * @dataProvider getValidationTestData
     */
    public function testValidationForCreateAction(array $formData, array $validationFields)
    {
        $this->assertFormHasValidationError(
            User::ROLE_ADMIN,
            '/admin/customer/create',
            'form[name=customer_edit_form]',
            $formData,
            $validationFields
        );
    }

    public function getValidationTestData()
    {
        return [
            [
                [
                    'customer_edit_form' => [
                        'name' => '',
                        'country' => '00',
                        'currency' => '00',
                        'timezone' => 'XXX'
                    ]
                ],
                [
                    '#customer_edit_form_name',
                    '#customer_edit_form_country',
                    '#customer_edit_form_currency',
                    '#customer_edit_form_timezone',
                ]
            ],
        ];
    }
}
