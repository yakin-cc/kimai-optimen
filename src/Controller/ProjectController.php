<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Configuration\SystemConfiguration;
use App\Entity\Customer;
use App\Entity\MetaTableTypeInterface;
use App\Entity\Project;
use App\Entity\ProjectComment;
use App\Entity\ProjectRate;
use App\Entity\Rate;
use App\Entity\Team;
use App\Event\ProjectDetailControllerEvent;
use App\Event\ProjectMetaDefinitionEvent;
use App\Event\ProjectMetaDisplayEvent;
use App\Export\Spreadsheet\EntityWithMetaFieldsExporter;
use App\Export\Spreadsheet\Writer\BinaryFileResponseWriter;
use App\Export\Spreadsheet\Writer\XlsxWriter;
use App\Form\ProjectCommentForm;
use App\Form\ProjectEditForm;
use App\Form\ProjectRateForm;
use App\Form\ProjectTeamPermissionForm;
use App\Form\Toolbar\ProjectToolbarForm;
use App\Form\Type\ProjectType;
use App\Project\ProjectDuplicationService;
use App\Project\ProjectService;
use App\Project\ProjectStatisticService;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRateRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\ActivityQuery;
use App\Repository\Query\ProjectQuery;
use App\Repository\TeamRepository;
use App\Utils\Context;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Controller used to manage projects.
 *
 * @Route(path="/admin/project")
 * @Security("is_granted('view_project') or is_granted('view_teamlead_project') or is_granted('view_team_project')")
 */
final class ProjectController extends AbstractController
{
    /**
     * @var ProjectRepository
     */
    private $repository;
    /**
     * @var SystemConfiguration
     */
    private $configuration;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var ProjectService
     */
    private $projectService;

    public function __construct(ProjectRepository $repository, SystemConfiguration $configuration, EventDispatcherInterface $dispatcher, ProjectService $projectService)
    {
        $this->repository = $repository;
        $this->configuration = $configuration;
        $this->dispatcher = $dispatcher;
        $this->projectService = $projectService;
    }

    /**
     * @Route(path="/", defaults={"page": 1}, name="admin_project", methods={"GET"})
     * @Route(path="/page/{page}", requirements={"page": "[1-9]\d*"}, name="admin_project_paginated", methods={"GET"})
     */
    public function indexAction($page, Request $request)
    {
        $query = new ProjectQuery();
        $query->setCurrentUser($this->getUser());
        $query->setPage($page);

        $form = $this->getToolbarForm($query);
        if ($this->handleSearch($form, $request)) {
            return $this->redirectToRoute('admin_project');
        }

        $entries = $this->repository->getPagerfantaForQuery($query);

        return $this->render('project/index.html.twig', [
            'entries' => $entries,
            'query' => $query,
            'toolbarForm' => $form->createView(),
            'metaColumns' => $this->findMetaColumns($query),
            'now' => $this->getDateTimeFactory()->createDateTime(),
        ]);
    }

    /**
     * @param ProjectQuery $query
     * @return MetaTableTypeInterface[]
     */
    protected function findMetaColumns(ProjectQuery $query): array
    {
        $event = new ProjectMetaDisplayEvent($query, ProjectMetaDisplayEvent::PROJECT);
        $this->dispatcher->dispatch($event);

        return $event->getFields();
    }

    /**
     * @Route(path="/{id}/permissions", name="admin_project_permissions", methods={"GET", "POST"})
     * @Security("is_granted('permissions', project)")
     */
    public function teamPermissions(Project $project, Request $request)
    {
        $form = $this->createForm(ProjectTeamPermissionForm::class, $project, [
            'action' => $this->generateUrl('admin_project_permissions', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->projectService->updateProject($project);
                $this->flashSuccess('action.update.success');

                if ($this->isGranted('view', $project)) {
                    return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
                }

                return $this->redirectToRoute('admin_project');
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('project/permissions.html.twig', [
            'project' => $project,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route(path="/create", name="admin_project_create", methods={"GET", "POST"})
     * @Route(path="/create/{customer}", name="admin_project_create_with_customer", methods={"GET", "POST"})
     * @Security("is_granted('create_project')")
     */
    public function createAction(Request $request, ?Customer $customer = null)
    {
        $project = $this->projectService->createNewProject($customer);

        $editForm = $this->createEditForm($project);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $this->projectService->saveNewProject($project, new Context($this->getUser()));
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $editForm->createView()
        ]);
    }

    /**
     * @Route(path="/{id}/comment_delete/{token}", name="project_comment_delete", methods={"GET"})
     * @Security("is_granted('edit', comment.getProject()) and is_granted('comments', comment.getProject())")
     */
    public function deleteCommentAction(ProjectComment $comment, string $token, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $projectId = $comment->getProject()->getId();

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('project.delete_comment', $token))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('project_details', ['id' => $projectId]);
        }

        $csrfTokenManager->refreshToken('project.delete_comment');

        try {
            $this->repository->deleteComment($comment);
        } catch (\Exception $ex) {
            $this->flashDeleteException($ex);
        }

        return $this->redirectToRoute('project_details', ['id' => $projectId]);
    }

    /**
     * @Route(path="/{id}/comment_add", name="project_comment_add", methods={"POST"})
     * @Security("is_granted('comments_create', project)")
     */
    public function addCommentAction(Project $project, Request $request)
    {
        $comment = new ProjectComment();
        $form = $this->getCommentForm($project, $comment);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->repository->saveComment($comment);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
    }

    /**
     * @Route(path="/{id}/comment_pin/{token}", name="project_comment_pin", methods={"GET"})
     * @Security("is_granted('edit', comment.getProject()) and is_granted('comments', comment.getProject())")
     */
    public function pinCommentAction(ProjectComment $comment, string $token, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $projectId = $comment->getProject()->getId();

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('project.pin_comment', $token))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('project_details', ['id' => $projectId]);
        }

        $csrfTokenManager->refreshToken('project.pin_comment');

        $comment->setPinned(!$comment->isPinned());
        try {
            $this->repository->saveComment($comment);
        } catch (\Exception $ex) {
            $this->flashUpdateException($ex);
        }

        return $this->redirectToRoute('project_details', ['id' => $projectId]);
    }

    /**
     * @Route(path="/{id}/create_team", name="project_team_create", methods={"GET"})
     * @Security("is_granted('create_team') and is_granted('edit', project)")
     */
    public function createDefaultTeamAction(Project $project, TeamRepository $teamRepository)
    {
        $defaultTeam = $teamRepository->findOneBy(['name' => $project->getName()]);
        if (null !== $defaultTeam) {
            $this->flashError('action.update.error', ['%reason%' => 'Team already existing']);

            return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
        }

        $defaultTeam = new Team();
        $defaultTeam->setName($project->getName());
        $defaultTeam->addTeamlead($this->getUser());
        $defaultTeam->addProject($project);

        try {
            $teamRepository->saveTeam($defaultTeam);
        } catch (\Exception $ex) {
            $this->flashUpdateException($ex);
        }

        return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
    }

    /**
     * @Route(path="/{id}/activities/{page}", defaults={"page": 1}, name="project_activities", methods={"GET", "POST"})
     * @Security("is_granted('view', project)")
     */
    public function activitiesAction(Project $project, int $page, ActivityRepository $activityRepository)
    {
        $query = new ActivityQuery();
        $query->setCurrentUser($this->getUser());
        $query->setPage($page);
        $query->setPageSize(5);
        $query->addProject($project);
        $query->setExcludeGlobals(true);
        $query->setShowBoth();
        $query->addOrderGroup('visible', ActivityQuery::ORDER_DESC);
        $query->addOrderGroup('name', ActivityQuery::ORDER_ASC);

        /* @var $entries Pagerfanta */
        $entries = $activityRepository->getPagerfantaForQuery($query);

        return $this->render('project/embed_activities.html.twig', [
            'project' => $project,
            'activities' => $entries,
            'page' => $page,
            'now' => $this->getDateTimeFactory()->createDateTime(),
        ]);
    }

    /**
     * @Route(path="/{id}/details", name="project_details", methods={"GET", "POST"})
     * @Security("is_granted('view', project)")
     */
    public function detailsAction(Project $project, TeamRepository $teamRepository, ProjectRateRepository $rateRepository, ProjectStatisticService $statisticService)
    {
        $event = new ProjectMetaDefinitionEvent($project);
        $this->dispatcher->dispatch($event);

        $stats = null;
        $defaultTeam = null;
        $commentForm = null;
        $attachments = [];
        $comments = null;
        $teams = null;
        $rates = [];
        $now = $this->getDateTimeFactory()->createDateTime();

        if ($this->isGranted('edit', $project)) {
            if ($this->isGranted('create_team')) {
                $defaultTeam = $teamRepository->findOneBy(['name' => $project->getName()]);
            }
            $rates = $rateRepository->getRatesForProject($project);
        }

        if ($this->isGranted('budget', $project) || $this->isGranted('time', $project)) {
            $stats = $statisticService->getBudgetStatisticModel($project, $now);
        }

        if ($this->isGranted('comments', $project)) {
            $comments = $this->repository->getComments($project);
        }

        if ($this->isGranted('comments_create', $project)) {
            $commentForm = $this->getCommentForm($project, new ProjectComment())->createView();
        }

        if ($this->isGranted('permissions', $project) || $this->isGranted('details', $project) || $this->isGranted('view_team')) {
            $teams = $project->getTeams();
        }

        // additional boxes by plugins
        $event = new ProjectDetailControllerEvent($project);
        $this->dispatcher->dispatch($event);
        $boxes = $event->getController();

        return $this->render('project/details.html.twig', [
            'project' => $project,
            'comments' => $comments,
            'commentForm' => $commentForm,
            'attachments' => $attachments,
            'stats' => $stats,
            'team' => $defaultTeam,
            'teams' => $teams,
            'rates' => $rates,
            'now' => $now,
            'boxes' => $boxes
        ]);
    }

    /**
     * @Route(path="/{id}/rate", name="admin_project_rate_add", methods={"GET", "POST"})
     * @Security("is_granted('edit', project)")
     */
    public function addRateAction(Project $project, Request $request, ProjectRateRepository $repository)
    {
        $rate = new ProjectRate();
        $rate->setProject($project);

        $form = $this->createForm(ProjectRateForm::class, $rate, [
            'action' => $this->generateUrl('admin_project_rate_add', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $repository->saveRate($rate);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('project/rates.html.twig', [
            'project' => $project,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route(path="/{id}/edit", name="admin_project_edit", methods={"GET", "POST"})
     * @Security("is_granted('edit', project)")
     */
    public function editAction(Project $project, Request $request)
    {
        $editForm = $this->createEditForm($project);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $this->projectService->updateProject($project);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $editForm->createView()
        ]);
    }

    /**
     * @Route(path="/{id}/duplicate/{token}", name="admin_project_duplicate", methods={"GET", "POST"})
     * @Security("is_granted('edit', project)")
     */
    public function duplicateAction(Project $project, string $token, ProjectDuplicationService $projectDuplicationService, CsrfTokenManagerInterface $csrfTokenManager)
    {
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('project.duplicate', $token))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('project_details', ['id' => $project->getId()]);
        }

        $csrfTokenManager->refreshToken('project.duplicate');

        $newProject = $projectDuplicationService->duplicate($project, $project->getName() . ' [COPY]');

        $this->flashSuccess('action.update.success');

        return $this->redirectToRoute('project_details', ['id' => $newProject->getId()]);
    }

    /**
     * @Route(path="/{id}/delete", name="admin_project_delete", methods={"GET", "POST"})
     * @Security("is_granted('delete', project)")
     */
    public function deleteAction(Project $project, Request $request, ProjectStatisticService $statisticService)
    {
        $stats = $statisticService->getProjectStatistics($project);

        $deleteForm = $this->createFormBuilder(null, [
                'attr' => [
                    'data-form-event' => 'kimai.projectDelete',
                    'data-msg-success' => 'action.delete.success',
                    'data-msg-error' => 'action.delete.error',
                ]
            ])
            ->add('project', ProjectType::class, [
                'ignore_project' => $project,
                'customers' => $project->getCustomer(),
                'query_builder_for_user' => true,
                'required' => false,
            ])
            ->setAction($this->generateUrl('admin_project_delete', ['id' => $project->getId()]))
            ->setMethod('POST')
            ->getForm();

        $deleteForm->handleRequest($request);

        if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
            try {
                $this->repository->deleteProject($project, $deleteForm->get('project')->getData());
                $this->flashSuccess('action.delete.success');
            } catch (\Exception $ex) {
                $this->flashDeleteException($ex);
            }

            return $this->redirectToRoute('admin_project');
        }

        return $this->render('project/delete.html.twig', [
            'project' => $project,
            'stats' => $stats,
            'form' => $deleteForm->createView(),
        ]);
    }

    /**
     * @Route(path="/export", name="project_export", methods={"GET"})
     */
    public function exportAction(Request $request, EntityWithMetaFieldsExporter $exporter)
    {
        $query = new ProjectQuery();
        $query->setCurrentUser($this->getUser());

        $form = $this->getToolbarForm($query);
        $form->setData($query);
        $form->submit($request->query->all(), false);

        if (!$form->isValid()) {
            $query->resetByFormError($form->getErrors());
        }

        $entries = $this->repository->getProjectsForQuery($query);

        $spreadsheet = $exporter->export(
            Project::class,
            $entries,
            new ProjectMetaDisplayEvent($query, ProjectMetaDisplayEvent::EXPORT)
        );
        $writer = new BinaryFileResponseWriter(new XlsxWriter(), 'kimai-projects');

        return $writer->getFileResponse($spreadsheet);
    }

    protected function getToolbarForm(ProjectQuery $query): FormInterface
    {
        return $this->createForm(ProjectToolbarForm::class, $query, [
            'action' => $this->generateUrl('admin_project', [
                'page' => $query->getPage(),
            ]),
            'method' => 'GET',
        ]);
    }

    private function getCommentForm(Project $project, ProjectComment $comment): FormInterface
    {
        if (null === $comment->getId()) {
            $comment->setProject($project);
            $comment->setCreatedBy($this->getUser());
        }

        return $this->createForm(ProjectCommentForm::class, $comment, [
            'action' => $this->generateUrl('project_comment_add', ['id' => $project->getId()]),
            'method' => 'POST',
        ]);
    }

    private function createEditForm(Project $project): FormInterface
    {
        $event = new ProjectMetaDefinitionEvent($project);
        $this->dispatcher->dispatch($event);

        $currency = $this->configuration->getCustomerDefaultCurrency();
        $url = $this->generateUrl('admin_project_create');

        if ($project->getId() !== null) {
            $url = $this->generateUrl('admin_project_edit', ['id' => $project->getId()]);
            $currency = $project->getCustomer()->getCurrency();
        }

        return $this->createForm(ProjectEditForm::class, $project, [
            'action' => $url,
            'method' => 'POST',
            'currency' => $currency,
            'timezone' => $this->getDateTimeFactory()->getTimezone()->getName(),
            'include_budget' => $this->isGranted('budget', $project),
            'include_time' => $this->isGranted('time', $project),
            'time_increment' => 15,
        ]);
    }
}
