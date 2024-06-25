<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\Team;
use App\Form\TeamCustomerForm;
use App\Form\TeamEditForm;
use App\Form\TeamProjectForm;
use App\Form\Toolbar\TeamToolbarForm;
use App\Repository\Query\TeamQuery;
use App\Repository\TeamRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @Route(path="/admin/teams")
 * @Security("is_granted('view_team')")
 */
final class TeamController extends AbstractController
{
    /**
     * @var TeamRepository
     */
    private $repository;

    public function __construct(TeamRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @Route(path="/", defaults={"page": 1}, name="admin_team", methods={"GET"})
     * @Route(path="/page/{page}", requirements={"page": "[1-9]\d*"}, name="admin_team_paginated", methods={"GET"})
     *
     * @param TeamRepository $repository
     * @param Request $request
     * @param int $page
     * @return Response
     */
    public function listTeams(TeamRepository $repository, Request $request, $page)
    {
        $query = new TeamQuery();
        $query->setPage($page);
        $query->setCurrentUser($this->getUser());

        $form = $this->getToolbarForm($query);
        if ($this->handleSearch($form, $request)) {
            return $this->redirectToRoute('admin_team');
        }

        $teams = $repository->getPagerfantaForQuery($query);

        return $this->render('team/index.html.twig', [
            'teams' => $teams,
            'query' => $query,
            'toolbarForm' => $form->createView(),
        ]);
    }

    /**
     * @Route(path="/create", name="admin_team_create", methods={"GET", "POST"})
     * @Security("is_granted('create_team')")
     *
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function createTeam(Request $request)
    {
        return $this->renderEditScreen(new Team(), $request);
    }

    /**
     * @Route(path="/{id}/duplicate/{token}", name="team_duplicate", methods={"GET", "POST"})
     * @Security("is_granted('edit', team) and is_granted('create_team')")
     */
    public function duplicateTeam(Team $team, string $token, CsrfTokenManagerInterface $csrfTokenManager)
    {
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('team.duplicate', $token))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('admin_team_edit', ['id' => $team->getId()]);
        }

        $csrfTokenManager->refreshToken('team.duplicate');

        $newTeam = clone $team;
        $newTeam->setName($team->getName() . ' [COPY]');

        try {
            $this->repository->saveTeam($newTeam);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('admin_team_edit', ['id' => $newTeam->getId()]);
        } catch (\Exception $ex) {
            $this->flashUpdateException($ex);
        }

        return $this->redirectToRoute('admin_team');
    }

    /**
     * @Route(path="/{id}/edit", name="admin_team_edit", methods={"GET", "POST"})
     * @Security("is_granted('edit', team)")
     */
    public function editAction(Team $team, Request $request)
    {
        return $this->renderEditScreen($team, $request);
    }

    /**
     * @Route(path="/{id}/edit_member", name="admin_team_member", methods={"GET", "POST"})
     * @Security("is_granted('edit', team)")
     */
    public function editMemberAction(Team $team, Request $request)
    {
        $editForm = $this->createForm(TeamEditForm::class, $team, [
            'action' => $this->generateUrl('admin_team_member', ['id' => $team->getId()]),
            'method' => 'POST',
        ]);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $this->repository->saveTeam($team);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('admin_team_edit', ['id' => $team->getId()]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('team/edit_member.html.twig', [
            'team' => $team,
            'form' => $editForm->createView(),
        ]);
    }

    private function renderEditScreen(Team $team, Request $request): Response
    {
        $customerForm = null;
        $projectForm = null;

        if ($team->getId() === null) {
            $url = $this->generateUrl('admin_team_create');
        } else {
            $url = $this->generateUrl('admin_team_edit', ['id' => $team->getId()]);
        }

        $editForm = $this->createForm(TeamEditForm::class, $team, [
            'action' => $url,
            'method' => 'POST',
        ]);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $this->repository->saveTeam($team);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('admin_team_edit', ['id' => $team->getId()]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        if (null !== $team->getId()) {
            $customerForm = $this->createForm(TeamCustomerForm::class, $team, [
                'method' => 'POST',
            ]);
            $customerForm->handleRequest($request);

            if ($customerForm->isSubmitted() && $customerForm->isValid()) {
                try {
                    $this->repository->saveTeam($team);
                    $this->flashSuccess('action.update.success');

                    return $this->redirectToRoute('admin_team_edit', ['id' => $team->getId()]);
                } catch (\Exception $ex) {
                    $this->flashUpdateException($ex);
                }
            }

            $projectForm = $this->createForm(TeamProjectForm::class, $team, [
                'method' => 'POST',
            ]);
            $projectForm->handleRequest($request);

            if ($projectForm->isSubmitted() && $projectForm->isValid()) {
                try {
                    $this->repository->saveTeam($team);
                    $this->flashSuccess('action.update.success');

                    return $this->redirectToRoute('admin_team_edit', ['id' => $team->getId()]);
                } catch (\Exception $ex) {
                    $this->flashUpdateException($ex);
                }
            }
        }

        return $this->render('team/edit.html.twig', [
            'team' => $team,
            'form' => $editForm->createView(),
            'customerForm' => $customerForm ? $customerForm->createView() : null,
            'projectForm' => $projectForm ? $projectForm->createView() : null,
        ]);
    }

    private function getToolbarForm(TeamQuery $query): FormInterface
    {
        return $this->createForm(TeamToolbarForm::class, $query, [
            'action' => $this->generateUrl('admin_team', [
                'page' => $query->getPage(),
            ]),
            'method' => 'GET',
        ]);
    }
}
