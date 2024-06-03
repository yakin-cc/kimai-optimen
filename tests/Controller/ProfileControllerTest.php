<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Tests\DataFixtures\TeamFixtures;
use App\Tests\DataFixtures\TimesheetFixtures;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * @group integration
 */
class ProfileControllerTest extends ControllerBaseTest
{
    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/profile/' . UserFixtures::USERNAME_USER);
    }

    public function testIndexActionWithoutData()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER);
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasProfileBox($client, 'John Doe');
        $this->assertHasAboutMeBox($client, UserFixtures::USERNAME_USER);

        $content = $client->getResponse()->getContent();
        $year = (new \DateTime())->format('Y');
        $this->assertStringContainsString('<h3 class="box-title">' . $year . '</h3>', $content);
        $this->assertStringContainsString('new Chart(', $content);
        $this->assertStringContainsString('<canvas id="userProfileChart' . $year . '"', $content);
    }

    public function testIndexAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);

        $dates = [
            new \DateTime('2018-06-13'),
            new \DateTime('2021-10-20'),
        ];

        foreach ($dates as $start) {
            $fixture = new TimesheetFixtures();
            $fixture->setAmount(10);
            $fixture->setUser($this->getUserByRole(User::ROLE_USER));
            $fixture->setStartDate($start);
            $this->importFixture($fixture);
        }

        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $content = $client->getResponse()->getContent();

        foreach ($dates as $start) {
            $year = $start->format('Y');
            $this->assertStringContainsString('<h3 class="box-title">' . $year . '</h3>', $content);
            $this->assertStringContainsString('<canvas id="userProfileChart' . $year . '"', $content);
        }

        $this->assertHasProfileBox($client, 'John Doe');
        $this->assertHasAboutMeBox($client, UserFixtures::USERNAME_USER);
    }

    protected function assertHasProfileBox(HttpKernelBrowser $client, string $username)
    {
        $profileBox = $client->getCrawler()->filter('div.box-user-profile');
        $this->assertEquals(1, $profileBox->count());
        $profileAvatar = $profileBox->filter('span.avatar');
        $this->assertEquals(1, $profileAvatar->count());
        $alt = $profileAvatar->attr('title');

        $this->assertEquals($username, $alt);
    }

    protected function assertHasAboutMeBox(HttpKernelBrowser $client, string $username)
    {
        $content = $client->getResponse()->getContent();

        $this->assertStringContainsString('About me', $content);
    }

    public function getTabTestData()
    {
        return [
            [User::ROLE_USER, UserFixtures::USERNAME_USER],
            [User::ROLE_SUPER_ADMIN, UserFixtures::USERNAME_SUPER_ADMIN],
        ];
    }

    /**
     * @dataProvider getTabTestData
     */
    public function testEditActionTabs($role, $username)
    {
        $client = $this->getClientForAuthenticatedUser($role);
        $this->request($client, '/profile/' . $username . '/edit');
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testIndexActionWithDifferentUsername()
    {
        $client = $this->getClientForAuthenticatedUser();
        $this->request($client, '/profile/' . UserFixtures::USERNAME_TEAMLEAD);
        $this->assertFalse($client->getResponse()->isSuccessful());
    }

    public function testEditAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/edit');

        /** @var User $user */
        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertEquals(UserFixtures::USERNAME_USER, $user->getUsername());
        $this->assertEquals('John Doe', $user->getAlias());
        $this->assertEquals('Developer', $user->getTitle());
        $this->assertEquals('john_user@example.com', $user->getEmail());
        $this->assertTrue($user->isEnabled());

        $form = $client->getCrawler()->filter('form[name=user_edit]')->form();
        $client->submit($form, [
            'user_edit' => [
                'alias' => 'Johnny',
                'title' => 'Code Monkey',
                'email' => 'updated@example.com',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/profile/' . urlencode(UserFixtures::USERNAME_USER) . '/edit'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);

        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertEquals(UserFixtures::USERNAME_USER, $user->getUsername());
        $this->assertEquals('Johnny', $user->getAlias());
        $this->assertEquals('Code Monkey', $user->getTitle());
        $this->assertEquals('updated@example.com', $user->getEmail());
        $this->assertTrue($user->isEnabled());
    }

    public function testEditActionWithActiveFlag()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/edit');

        $form = $client->getCrawler()->filter('form[name=user_edit]')->form();
        $client->submit($form, [
            'user_edit' => [
                'alias' => 'Johnny',
                'title' => 'Code Monkey',
                'email' => 'updated@example.com',
                'enabled' => false,
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/profile/' . urlencode(UserFixtures::USERNAME_USER) . '/edit'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);

        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertEquals(UserFixtures::USERNAME_USER, $user->getUsername());
        $this->assertEquals('Johnny', $user->getAlias());
        $this->assertEquals('Code Monkey', $user->getTitle());
        $this->assertEquals('updated@example.com', $user->getEmail());
        $this->assertFalse($user->isEnabled());
    }

    public function testPasswordAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/password');

        /** @var User $user */
        $user = $this->getUserByRole(User::ROLE_USER);

        /** @var EncoderFactoryInterface $passwordEncoder */
        $passwordEncoder = static::$kernel->getContainer()->get('test.PasswordEncoder');

        $this->assertTrue($passwordEncoder->getEncoder($user)->isPasswordValid($user->getPassword(), UserFixtures::DEFAULT_PASSWORD, $user->getSalt()));
        $this->assertFalse($passwordEncoder->getEncoder($user)->isPasswordValid($user->getPassword(), 'test123', $user->getSalt()));
        $this->assertEquals(UserFixtures::USERNAME_USER, $user->getUsername());

        $form = $client->getCrawler()->filter('form[name=user_password]')->form();
        $client->submit($form, [
            'user_password' => [
                'plainPassword' => [
                    'first' => 'test1234',
                    'second' => 'test1234',
                ]
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/profile/' . urlencode(UserFixtures::USERNAME_USER) . '/password'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);

        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertFalse($passwordEncoder->getEncoder($user)->isPasswordValid($user->getPassword(), UserFixtures::DEFAULT_PASSWORD, $user->getSalt()));
        $this->assertTrue($passwordEncoder->getEncoder($user)->isPasswordValid($user->getPassword(), 'test1234', $user->getSalt()));
    }

    public function testPasswordActionFailsIfPasswordLengthToShort()
    {
        $this->assertFormHasValidationError(
            User::ROLE_USER,
            '/profile/' . UserFixtures::USERNAME_USER . '/password',
            'form[name=user_password]',
            [
                'user_password' => [
                    'plainPassword' => [
                        'first' => 'abcdef1',
                        'second' => 'abcdef1',
                    ]
                ]
            ],
            ['#user_password_plainPassword_first']
        );
    }

    public function testApiTokenAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/api-token');

        /** @var User $user */
        $user = $this->getUserByRole(User::ROLE_USER);
        /** @var EncoderFactoryInterface $passwordEncoder */
        $passwordEncoder = static::$kernel->getContainer()->get('test.PasswordEncoder');

        $this->assertTrue($passwordEncoder->getEncoder($user)->isPasswordValid($user->getApiToken(), UserFixtures::DEFAULT_API_TOKEN, $user->getSalt()));
        $this->assertFalse($passwordEncoder->getEncoder($user)->isPasswordValid($user->getApiToken(), 'test1234', $user->getSalt()));
        $this->assertEquals(UserFixtures::USERNAME_USER, $user->getUsername());

        $form = $client->getCrawler()->filter('form[name=user_api_token]')->form();
        $client->submit($form, [
            'user_api_token' => [
                'plainApiToken' => [
                    'first' => 'test1234',
                    'second' => 'test1234',
                ]
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/profile/' . urlencode(UserFixtures::USERNAME_USER) . '/api-token'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);

        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertFalse($passwordEncoder->getEncoder($user)->isPasswordValid($user->getApiToken(), UserFixtures::DEFAULT_API_TOKEN, $user->getSalt()));
        $this->assertTrue($passwordEncoder->getEncoder($user)->isPasswordValid($user->getApiToken(), 'test1234', $user->getSalt()));
    }

    public function testApiTokenActionFailsIfPasswordLengthToShort()
    {
        $this->assertFormHasValidationError(
            User::ROLE_USER,
            '/profile/' . UserFixtures::USERNAME_USER . '/api-token',
            'form[name=user_api_token]',
            [
                'user_api_token' => [
                    'plainApiToken' => [
                        'first' => 'abcdef1',
                        'second' => 'abcdef1',
                    ]
                ]
            ],
            ['#user_api_token_plainApiToken_first']
        );
    }

    public function testRolesActionIsSecured()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/roles');
        $this->assertFalse($client->getResponse()->isSuccessful());
    }

    public function testRolesAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/roles');

        /** @var User $user */
        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertEquals(['ROLE_USER'], $user->getRoles());

        $form = $client->getCrawler()->filter('form[name=user_roles]')->form();
        $client->submit($form, [
            'user_roles[roles]' => [
                0 => 'ROLE_TEAMLEAD',
                2 => 'ROLE_SUPER_ADMIN',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/profile/' . urlencode(UserFixtures::USERNAME_USER) . '/roles'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);

        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertEquals(['ROLE_TEAMLEAD', 'ROLE_SUPER_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testTeamsActionIsSecured()
    {
        $this->assertUrlIsSecured('/profile/' . UserFixtures::USERNAME_USER . '/teams');
    }

    public function testTeamsActionIsSecuredForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_TEAMLEAD, '/profile/' . UserFixtures::USERNAME_USER . '/teams');
    }

    public function testTeamsAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        /** @var User $user */
        $user = $this->getUserByRole(User::ROLE_USER);

        $fixture = new TeamFixtures();
        $fixture->setAmount(3);
        $fixture->setAddCustomer(true);
        $fixture->setAddUser(false);
        $fixture->addUserToIgnore($user);
        $this->importFixture($fixture);

        $this->request($client, '/profile/' . UserFixtures::USERNAME_USER . '/teams');

        /** @var User $user */
        $user = $this->getUserByRole(User::ROLE_USER);
        $this->assertEquals([], $user->getTeams());

        $form = $client->getCrawler()->filter('form[name=user_teams]')->form();
        /** @var ChoiceFormField $team */
        $team = $form->get('user_teams[teams][0]');
        $team->tick();

        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/profile/' . urlencode(UserFixtures::USERNAME_USER) . '/teams'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);

        $user = $this->getUserByRole(User::ROLE_USER);

        $this->assertCount(1, $user->getTeams());
    }

    public function getPreferencesTestData()
    {
        return [
            // assert that the user doesn't have the "hourly-rate_own_profile" permission
            [User::ROLE_USER, UserFixtures::USERNAME_USER, 82, 82, 'ar', null],
            // teamleads are allowed to update their own hourly rate, but not other peoples hourly rate
            [User::ROLE_TEAMLEAD, UserFixtures::USERNAME_TEAMLEAD, 35, 37.5, 'ar', 19.54],
            // admins are allowed to update their own hourly rate, but not other peoples hourly rate
            [User::ROLE_ADMIN, UserFixtures::USERNAME_ADMIN, 81, 37.5, 'ar', 19.54],
            // super-admins are allowed to update other peoples hourly rate
            [User::ROLE_SUPER_ADMIN, UserFixtures::USERNAME_ADMIN, 81, 37.5, 'en', 19.54],
            // super-admins are allowed to update their own hourly rate
            [User::ROLE_SUPER_ADMIN, UserFixtures::USERNAME_SUPER_ADMIN, 46, 37.5, 'ar', 19.54],
        ];
    }

    /**
     * @dataProvider getPreferencesTestData
     */
    public function testPreferencesAction($role, $username, $hourlyRateOriginal, $hourlyRate, $expectedLocale, $expectedInternalRate)
    {
        $client = $this->getClientForAuthenticatedUser($role);
        $this->request($client, '/profile/' . $username . '/prefs');

        /** @var User $user */
        $user = $this->getUserByName($username);

        $this->assertEquals($hourlyRateOriginal, $user->getPreferenceValue(UserPreference::HOURLY_RATE));
        $this->assertNull($user->getPreferenceValue(UserPreference::INTERNAL_RATE));
        $this->assertNull($user->getPreferenceValue(UserPreference::SKIN));

        $form = $client->getCrawler()->filter('form[name=user_preferences_form]')->form();
        $client->submit($form, [
            'user_preferences_form' => [
                'preferences' => [
                    0 => ['name' => UserPreference::HOURLY_RATE, 'value' => 37.5],
                    1 => ['name' => UserPreference::INTERNAL_RATE, 'value' => 19.54],
                    2 => ['name' => UserPreference::TIMEZONE, 'value' => 'America/Creston'],
                    3 => ['name' => UserPreference::LOCALE, 'value' => 'ar'],
                    4 => ['name' => UserPreference::FIRST_WEEKDAY, 'value' => 'sunday'],
                    6 => ['name' => UserPreference::SKIN, 'value' => 'blue'],
                ]
            ]
        ]);

        $targetUrl = '/' . $expectedLocale . '/profile/' . urlencode($username) . '/prefs';

        $this->assertIsRedirect($client, $targetUrl);
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);

        $user = $this->getUserByName($username);

        $this->assertEquals($hourlyRate, $user->getPreferenceValue(UserPreference::HOURLY_RATE));
        $this->assertEquals($expectedInternalRate, $user->getPreferenceValue(UserPreference::INTERNAL_RATE));
        $this->assertEquals('America/Creston', $user->getPreferenceValue(UserPreference::TIMEZONE));
        $this->assertEquals('America/Creston', $user->getTimezone());
        $this->assertEquals('ar', $user->getPreferenceValue(UserPreference::LOCALE));
        $this->assertEquals('ar', $user->getLanguage());
        $this->assertEquals('ar', $user->getLocale());
        $this->assertEquals('blue', $user->getPreferenceValue(UserPreference::SKIN));
        $this->assertEquals('sunday', $user->getPreferenceValue(UserPreference::FIRST_WEEKDAY));
        $this->assertEquals('sunday', $user->getFirstDayOfWeek());
    }
}
