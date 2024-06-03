<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\API;

use App\Entity\Team;
use App\Entity\User;
use App\Tests\DataFixtures\TeamFixtures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group integration
 */
class TeamControllerTest extends APIControllerBaseTest
{
    /**
     * @return Team[]
     */
    protected function importTeamFixtures(): array
    {
        $fixture = new TeamFixtures();
        $fixture->setAmount(1);

        return $this->importFixture($fixture);
    }

    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/api/teams');
    }

    public function getRoleTestData()
    {
        return [
            [User::ROLE_USER],
            [User::ROLE_TEAMLEAD],
        ];
    }

    /**
     * @dataProvider getRoleTestData
     */
    public function testIsSecureForRole(string $role)
    {
        $this->assertUrlIsSecuredForRole($role, '/api/teams');
    }

    public function testGetCollection()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->importTeamFixtures();
        $this->assertAccessIsGranted($client, '/api/teams');
        $result = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        self::assertEquals(2, \count($result));
        self::assertApiResponseTypeStructure('TeamCollection', $result[0]);
    }

    public function testGetEntity()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $teams = $this->importTeamFixtures();
        $id = $teams[0]->getId();

        $this->assertAccessIsGranted($client, '/api/teams/' . $id);
        $result = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
    }

    public function testNotFound()
    {
        $this->assertEntityNotFound(User::ROLE_ADMIN, '/api/teams/3');
    }

    public function testDeleteActionWithUnknownTeam()
    {
        $this->assertEntityNotFoundForDelete(User::ROLE_ADMIN, '/api/teams/255');
    }

    public function testPostAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1]
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        $this->assertNotEmpty($result['id']);
    }

    public function testPostActionWithInvalidUser()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $data = [
            'name' => 'foo',
            'teamlead' => 1,
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $response = $client->getResponse();
        $this->assertFalse($response->isSuccessful());
        self::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        self::assertEquals('Access denied.', $json['message']);
    }

    public function testPostActionWithValidationErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => '',
            'members' => [
                ['user' => 9999, 'teamlead' => 1]
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertApiCallValidationError($response, ['name', 'members.0.user']);
    }

    public function testPatchAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => true],
                ['user' => 5, 'teamlead' => true],
            ]
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        $updateId = $result['id'];

        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 2, 'teamlead' => true],
                ['user' => 1, 'teamlead' => false],
                ['user' => 4, 'teamlead' => true],
            ]
        ];

        $this->request($client, '/api/teams/' . $updateId, 'PATCH', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        $this->assertNotEmpty($result['id']);
        self::assertCount(3, $result['users']);

        $this->request($client, '/api/teams/' . $updateId);
        $result = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(3, $result['users']);

        self::assertFalse($result['members'][1]['teamlead']);
        self::assertEquals(1, $result['members'][1]['user']['id']);
        self::assertEquals('clara_customer', $result['members'][1]['user']['username']);

        self::assertTrue($result['members'][2]['teamlead']);
        self::assertEquals(4, $result['members'][2]['user']['id']);
        self::assertEquals('tony_teamlead', $result['members'][2]['user']['username']);

        self::assertTrue(true, $result['members'][0]['teamlead']);
        self::assertEquals(2, $result['members'][0]['user']['id']);
        self::assertEquals('john_user', $result['members'][0]['user']['username']);
    }

    public function testPatchActionWithValidationErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        $data = [
            'name' => '1',
            'members' => [
                ['user' => 9999, 'teamlead' => 1],
            ],
        ];
        $this->request($client, '/api/teams/' . $result['id'], 'PATCH', [], json_encode($data));

        $response = $client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertApiCallValidationError($response, ['name', 'members.0.user']);
    }

    public function testDeleteAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $teams = $this->importTeamFixtures();
        $id = $teams[0]->getId();
        $this->assertAccessIsGranted($client, '/api/teams/' . $id);
        $result = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        $this->assertNotEmpty($result['id']);
        $id = $result['id'];

        $this->request($client, '/api/teams/' . $id, 'DELETE');
        $this->assertTrue($client->getResponse()->isSuccessful());
        self::assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
        $this->assertEmpty($client->getResponse()->getContent());
    }

    public function testPostMemberAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1]
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $result['users']);

        $this->request($client, '/api/teams/' . $result['id'] . '/members/2', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(2, $result['users']);
    }

    public function testPostMemberActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/members/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  user not found
        $this->request($client, '/api/teams/' . $result['id'] . '/members/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('User not found', $json['message']);

        // add user
        $this->request($client, '/api/teams/' . $result['id'] . '/members/5', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());

        // cannot add existing member
        $this->request($client, '/api/teams/' . $result['id'] . '/members/5', 'POST');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('User is already member of the team', $json['message']);
    }

    public function testDeleteMemberAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(4, $result['users']);

        $this->request($client, '/api/teams/' . $result['id'] . '/members/2', 'DELETE');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(3, $result['users']);
    }

    public function testDeleteMemberActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/members/999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  user not found
        $this->request($client, '/api/teams/' . $result['id'] . '/members/999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('User not found', $json['message']);

        // remove user
        $this->request($client, '/api/teams/' . $result['id'] . '/members/2', 'DELETE');
        $this->assertTrue($client->getResponse()->isSuccessful());

        // cannot remove non-member
        $this->request($client, '/api/teams/' . $result['id'] . '/members/2', 'DELETE');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('User is not a member of the team', $json['message']);

        // cannot remove teamlead
        $this->request($client, '/api/teams/' . $result['id'] . '/members/1', 'DELETE');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Cannot remove teamlead', $json['message']);
    }

    public function testPostCustomerAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $result['customers']);

        $this->request($client, '/api/teams/' . $result['id'] . '/customers/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(1, $result['customers']);
        self::assertEquals(1, $result['customers'][0]['id']);
    }

    public function testPostCustomerActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/customers/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  customer not found
        $this->request($client, '/api/teams/' . $result['id'] . '/customers/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Customer not found', $json['message']);

        // add customer
        $this->request($client, '/api/teams/' . $result['id'] . '/customers/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $result['customers']);

        // cannot add existing customer
        $this->request($client, '/api/teams/' . $result['id'] . '/customers/1', 'POST');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team has already access to customer', $json['message']);
    }

    public function testDeleteCustomerAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $result['customers']);

        // add customer
        $this->request($client, '/api/teams/' . $result['id'] . '/customers/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $result['customers']);

        $this->request($client, '/api/teams/' . $result['id'] . '/customers/1', 'DELETE');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(0, $result['customers']);
    }

    public function testDeleteCustomerActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/customers/999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  customer not found
        $this->request($client, '/api/teams/' . $result['id'] . '/customers/999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Customer not found', $json['message']);

        // cannot remove customer
        $this->request($client, '/api/teams/' . $result['id'] . '/customers/1', 'DELETE');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Customer is not assigned to the team', $json['message']);
    }

    public function testPostProjectAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $result['projects']);
        $this->request($client, '/api/teams/' . $result['id'] . '/projects/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(1, $result['projects']);
        self::assertEquals(1, $result['projects'][0]['id']);
    }

    public function testPostProjectActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/projects/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  project not found
        $this->request($client, '/api/teams/' . $result['id'] . '/projects/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Project not found', $json['message']);

        // add project
        $this->request($client, '/api/teams/' . $result['id'] . '/projects/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $result['projects']);

        // cannot add existing project
        $this->request($client, '/api/teams/' . $result['id'] . '/projects/1', 'POST');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team has already access to project', $json['message']);
    }

    public function testDeleteProjectAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $result['projects']);

        // add project
        $this->request($client, '/api/teams/' . $result['id'] . '/projects/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $result['projects']);

        $this->request($client, '/api/teams/' . $result['id'] . '/projects/1', 'DELETE');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(0, $result['projects']);
    }

    public function testDeleteProjectActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/projects/999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  project not found
        $this->request($client, '/api/teams/' . $result['id'] . '/projects/999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Project not found', $json['message']);

        // cannot remove project
        $this->request($client, '/api/teams/' . $result['id'] . '/projects/1', 'DELETE');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Project is not assigned to the team', $json['message']);
    }

    public function testPostActivityAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $result['activities']);
        $this->request($client, '/api/teams/' . $result['id'] . '/activities/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(1, $result['activities']);
        self::assertEquals(1, $result['activities'][0]['id']);
    }

    public function testPostActivityActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/activities/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  activity not found
        $this->request($client, '/api/teams/' . $result['id'] . '/activities/999', 'POST');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Activity not found', $json['message']);

        // add activity
        $this->request($client, '/api/teams/' . $result['id'] . '/activities/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $result['activities']);

        // cannot add existing activity
        $this->request($client, '/api/teams/' . $result['id'] . '/activities/1', 'POST');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team has already access to activity', $json['message']);
    }

    public function testDeleteActivityAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(0, $result['activities']);

        // add activity
        $this->request($client, '/api/teams/' . $result['id'] . '/activities/1', 'POST');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $result['activities']);

        $this->request($client, '/api/teams/' . $result['id'] . '/activities/1', 'DELETE');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($result);
        self::assertApiResponseTypeStructure('TeamEntity', $result);
        self::assertCount(0, $result['activities']);
    }

    public function testDeleteActivityActionErrors()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $data = [
            'name' => 'foo',
            'members' => [
                ['user' => 1, 'teamlead' => 1],
                ['user' => 2, 'teamlead' => 0],
                ['user' => 4, 'teamlead' => 0],
                ['user' => 5, 'teamlead' => 0],
            ],
        ];
        $this->request($client, '/api/teams', 'POST', [], json_encode($data));
        $this->assertTrue($client->getResponse()->isSuccessful());
        $result = json_decode($client->getResponse()->getContent(), true);

        //  team not found
        $this->request($client, '/api/teams/999/activities/9999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Team not found', $json['message']);

        //  activity not found
        $this->request($client, '/api/teams/' . $result['id'] . '/activities/9999', 'DELETE');
        self::assertEquals(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Activity not found', $json['message']);

        // cannot remove activity
        $this->request($client, '/api/teams/' . $result['id'] . '/activities/1', 'DELETE');
        self::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $json = json_decode($client->getResponse()->getContent(), true);
        self::assertEquals('Activity is not assigned to the team', $json['message']);
    }
}
