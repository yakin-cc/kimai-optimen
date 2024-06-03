<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Team;
use App\Entity\User;
use App\Tests\DataFixtures\TeamFixtures;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * @group integration
 */
class TeamControllerTest extends ControllerBaseTest
{
    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/admin/teams/');
    }

    public function testIsSecureForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_TEAMLEAD, '/admin/teams/');
    }

    public function testIndexAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();
        $fixture = new TeamFixtures();
        $fixture->setAmount(5);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/teams/');
        $this->assertPageActions($client, [
            'search' => '#',
            'create' => $this->createUrl('/admin/teams/create'),
            'help' => 'https://www.kimai.org/documentation/teams.html'
        ]);
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_admin_teams', 6);
    }

    public function testIndexActionWithSearchTermQuery()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();
        $fixture = new TeamFixtures();
        $fixture->setAmount(5);
        $fixture->setCallback(function (Team $team) {
            $team->setName($team->getName() . '- fantastic team with foooo bar magic');
        });
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/teams/');

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $client->submit($form, [
            'searchTerm' => 'foo',
        ]);

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_admin_teams', 5);
    }

    public function testCreateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/teams/create');
        $form = $client->getCrawler()->filter('form[name=team_edit_form]')->form();

        $this->assertEquals('', $form->get('team_edit_form[name]')->getValue());

        $values = $form->getPhpValues();
        $values['team_edit_form']['name'] = 'Test Team' . uniqid();
        $values['team_edit_form']['members'][0]['user'] = 5;
        $values['team_edit_form']['members'][0]['teamlead'] = 1;
        $client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

        $this->assertIsRedirect($client, '/edit');
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);
        $this->assertHasCustomerAndProjectPermissionBoxes($client);
    }

    protected function assertHasCustomerAndProjectPermissionBoxes(HttpKernelBrowser $client)
    {
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('Grant access to customers', $content);
        $this->assertStringContainsString('Grant access to projects', $content);
        $this->assertEquals(1, $client->getCrawler()->filter('form[name=team_customer_form]')->count());
        $this->assertEquals(1, $client->getCrawler()->filter('form[name=team_project_form]')->count());
    }

    public function testEditAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/teams/1/edit');
        $form = $client->getCrawler()->filter('form[name=team_edit_form]')->form();

        $client->submit($form, [
            'team_edit_form' => [
                'name' => 'Test Team 2'
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/teams/1/edit'));
        $client->followRedirect();
        $editForm = $client->getCrawler()->filter('form[name=team_edit_form]')->form();
        $this->assertEquals('Test Team 2', $editForm->get('team_edit_form[name]')->getValue());
    }

    public function testEditMemberAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $em = $this->getEntityManager();
        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/teams/1/edit_member');
        $form = $client->getCrawler()->filter('form[name=team_edit_form]')->form();
        $client->submit($form, [
            'team_edit_form' => [
                'name' => 'Test Team 2'
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/teams/1/edit'));
        $client->followRedirect();
        $editForm = $client->getCrawler()->filter('form[name=team_edit_form]')->form();
        $this->assertEquals('Test Team 2', $editForm->get('team_edit_form[name]')->getValue());
    }

    public function testEditCustomerAccessAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $fixture->setAddCustomer(false);
        $this->importFixture($fixture);

        $team = $em->getRepository(Team::class)->find(1);
        self::assertEquals(0, \count($team->getCustomers()));

        $this->assertAccessIsGranted($client, '/admin/teams/1/edit');
        $form = $client->getCrawler()->filter('form[name=team_customer_form]')->form();

        /** @var ChoiceFormField $customer */
        $customer = $form->get('team_customer_form[customers][0]');
        $customer->tick();

        $client->submit($form);
        $this->assertIsRedirect($client, $this->createUrl('/admin/teams/1/edit'));

        $team = $em->getRepository(Team::class)->find(1);
        self::assertEquals(1, \count($team->getCustomers()));
    }

    public function testEditProjectAccessAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $fixture->setAddCustomer(false);
        $this->importFixture($fixture);

        $team = $em->getRepository(Team::class)->find(1);
        self::assertEquals(0, \count($team->getProjects()));

        $this->assertAccessIsGranted($client, '/admin/teams/1/edit');
        $form = $client->getCrawler()->filter('form[name=team_project_form]')->form();

        /** @var ChoiceFormField $customer */
        $customer = $form->get('team_project_form[projects]');
        $customer->select([1]);

        $client->submit($form);
        $this->assertIsRedirect($client, $this->createUrl('/admin/teams/1/edit'));

        $team = $em->getRepository(Team::class)->find(1);
        self::assertEquals(1, \count($team->getProjects()));
    }

    public function testDuplicateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $token = self::$container->get('security.csrf.token_manager')->getToken('team.duplicate');

        $this->request($client, '/admin/teams/1/duplicate/' . $token);
        $this->assertIsRedirect($client, '/edit');
        $client->followRedirect();
        $node = $client->getCrawler()->filter('#team_edit_form_name');
        self::assertEquals(1, $node->count());
        self::assertEquals('Test team [COPY]', $node->attr('value'));
    }

    public function testDuplicateActionWithInvalidCsrf()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertInvalidCsrfToken($client, '/admin/teams/1/duplicate/rsetdzfukgli78t6r5uedtjfzkugl', $this->createUrl('/admin/teams/1/edit'));
    }
}
