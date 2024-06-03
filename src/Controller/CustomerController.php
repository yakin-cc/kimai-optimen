<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Configuration\SystemConfiguration;
use App\Customer\CustomerStatisticService;
use App\Entity\Customer;
use App\Entity\CustomerComment;
use App\Entity\CustomerRate;
use App\Entity\MetaTableTypeInterface;
use App\Entity\Team;
use App\Event\CustomerDetailControllerEvent;
use App\Event\CustomerMetaDefinitionEvent;
use App\Event\CustomerMetaDisplayEvent;
use App\Export\Spreadsheet\EntityWithMetaFieldsExporter;
use App\Export\Spreadsheet\Writer\BinaryFileResponseWriter;
use App\Export\Spreadsheet\Writer\XlsxWriter;
use App\Form\CustomerCommentForm;
use App\Form\CustomerEditForm;
use App\Form\CustomerRateForm;
use App\Form\CustomerTeamPermissionForm;
use App\Form\Toolbar\CustomerToolbarForm;
use App\Form\Type\CustomerType;
use App\Repository\CustomerRateRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\CustomerQuery;
use App\Repository\Query\ProjectQuery;
use App\Repository\TeamRepository;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Controller used to manage customer in the admin part of the site.
 *
 * @Route(path="/admin/customer")
 * @Security("is_granted('view_customer') or is_granted('view_teamlead_customer') or is_granted('view_team_customer')")
 */
final class CustomerController extends AbstractController
{
    /**
     * @var CustomerRepository
     */
    private $repository;
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(CustomerRepository $repository, EventDispatcherInterface $dispatcher)
    {
        $this->repository = $repository;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @Route(path="/", defaults={"page": 1}, name="admin_customer", methods={"GET"})
     * @Route(path="/page/{page}", requirements={"page": "[1-9]\d*"}, name="admin_customer_paginated", methods={"GET"})
     */
    public function indexAction($page, Request $request)
    {
        $query = new CustomerQuery();
        $query->setCurrentUser($this->getUser());
        $query->setPage($page);

        $form = $this->getToolbarForm($query);
        if ($this->handleSearch($form, $request)) {
            return $this->redirectToRoute('admin_customer');
        }

        $entries = $this->repository->getPagerfantaForQuery($query);

        return $this->render('customer/index.html.twig', [
            'entries' => $entries,
            'query' => $query,
            'toolbarForm' => $form->createView(),
            'metaColumns' => $this->findMetaColumns($query),
            'now' => $this->getDateTimeFactory()->createDateTime(),
        ]);
    }

    /**
     * @param CustomerQuery $query
     * @return MetaTableTypeInterface[]
     */
    private function findMetaColumns(CustomerQuery $query): array
    {
        $event = new CustomerMetaDisplayEvent($query, CustomerMetaDisplayEvent::CUSTOMER);
        $this->dispatcher->dispatch($event);

        return $event->getFields();
    }

    /**
     * @Route(path="/create", name="admin_customer_create", methods={"GET", "POST"})
     * @Security("is_granted('create_customer')")
     */
    public function createAction(Request $request, SystemConfiguration $configuration)
    {
        $timezone = date_default_timezone_get();
        if (null !== $configuration->getCustomerDefaultTimezone()) {
            $timezone = $configuration->getCustomerDefaultTimezone();
        }

        $customer = new Customer();
        $customer->setCountry($configuration->getCustomerDefaultCountry());
        $customer->setCurrency($configuration->getCustomerDefaultCurrency());
        $customer->setTimezone($timezone);

        return $this->renderCustomerForm($customer, $request);
    }

    /**
     * @Route(path="/{id}/permissions", name="admin_customer_permissions", methods={"GET", "POST"})
     * @Security("is_granted('permissions', customer)")
     */
    public function teamPermissionsAction(Customer $customer, Request $request)
    {
        $form = $this->createForm(CustomerTeamPermissionForm::class, $customer, [
            'action' => $this->generateUrl('admin_customer_permissions', ['id' => $customer->getId()]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->repository->saveCustomer($customer);
                $this->flashSuccess('action.update.success');

                if ($this->isGranted('view', $customer)) {
                    return $this->redirectToRoute('customer_details', ['id' => $customer->getId()]);
                }

                return $this->redirectToRoute('admin_customer');
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('customer/permissions.html.twig', [
            'customer' => $customer,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route(path="/{id}/comment_delete/{token}", name="customer_comment_delete", methods={"GET"})
     * @Security("is_granted('edit', comment.getCustomer()) and is_granted('comments', comment.getCustomer())")
     */
    public function deleteCommentAction(CustomerComment $comment, string $token, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $customerId = $comment->getCustomer()->getId();

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('customer.delete_comment', $token))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('customer_details', ['id' => $customerId]);
        }

        $csrfTokenManager->refreshToken('customer.delete_comment');

        try {
            $this->repository->deleteComment($comment);
        } catch (\Exception $ex) {
            $this->flashDeleteException($ex);
        }

        return $this->redirectToRoute('customer_details', ['id' => $customerId]);
    }

    /**
     * @Route(path="/{id}/comment_add", name="customer_comment_add", methods={"POST"})
     * @Security("is_granted('comments_create', customer)")
     */
    public function addCommentAction(Customer $customer, Request $request)
    {
        $comment = new CustomerComment();
        $form = $this->getCommentForm($customer, $comment);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->repository->saveComment($comment);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->redirectToRoute('customer_details', ['id' => $customer->getId()]);
    }

    /**
     * @Route(path="/{id}/comment_pin/{token}", name="customer_comment_pin", methods={"GET"})
     * @Security("is_granted('edit', comment.getCustomer()) and is_granted('comments', comment.getCustomer())")
     */
    public function pinCommentAction(CustomerComment $comment, string $token, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $customerId = $comment->getCustomer()->getId();

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('customer.pin_comment', $token))) {
            $this->flashError('action.csrf.error');

            return $this->redirectToRoute('customer_details', ['id' => $customerId]);
        }

        $csrfTokenManager->refreshToken('customer.pin_comment');

        $comment->setPinned(!$comment->isPinned());
        try {
            $this->repository->saveComment($comment);
        } catch (\Exception $ex) {
            $this->flashUpdateException($ex);
        }

        return $this->redirectToRoute('customer_details', ['id' => $customerId]);
    }

    /**
     * @Route(path="/{id}/create_team", name="customer_team_create", methods={"GET"})
     * @Security("is_granted('create_team') and is_granted('permissions', customer)")
     */
    public function createDefaultTeamAction(Customer $customer, TeamRepository $teamRepository)
    {
        $defaultTeam = $teamRepository->findOneBy(['name' => $customer->getName()]);
        if (null !== $defaultTeam) {
            $this->flashError('action.update.error', ['%reason%' => 'Team already existing']);

            return $this->redirectToRoute('customer_details', ['id' => $customer->getId()]);
        }

        $defaultTeam = new Team();
        $defaultTeam->setName($customer->getName());
        $defaultTeam->addTeamlead($this->getUser());
        $defaultTeam->addCustomer($customer);

        try {
            $teamRepository->saveTeam($defaultTeam);
        } catch (\Exception $ex) {
            $this->flashUpdateException($ex);
        }

        return $this->redirectToRoute('customer_details', ['id' => $customer->getId()]);
    }

    /**
     * @Route(path="/{id}/projects/{page}", defaults={"page": 1}, name="customer_projects", methods={"GET", "POST"})
     * @Security("is_granted('view', customer)")
     */
    public function projectsAction(Customer $customer, int $page, ProjectRepository $projectRepository)
    {
        $query = new ProjectQuery();
        $query->setCurrentUser($this->getUser());
        $query->setPage($page);
        $query->setPageSize(5);
        $query->addCustomer($customer);
        $query->setShowBoth();
        $query->addOrderGroup('visible', ProjectQuery::ORDER_DESC);
        $query->addOrderGroup('name', ProjectQuery::ORDER_ASC);

        /* @var $entries Pagerfanta */
        $entries = $projectRepository->getPagerfantaForQuery($query);

        return $this->render('customer/embed_projects.html.twig', [
            'customer' => $customer,
            'projects' => $entries,
            'page' => $page,
            'now' => $this->getDateTimeFactory()->createDateTime(),
        ]);
    }

    /**
     * @Route(path="/{id}/details", name="customer_details", methods={"GET", "POST"})
     * @Security("is_granted('view', customer)")
     */
    public function detailsAction(Customer $customer, TeamRepository $teamRepository, CustomerRateRepository $rateRepository, CustomerStatisticService $statisticService)
    {
        $event = new CustomerMetaDefinitionEvent($customer);
        $this->dispatcher->dispatch($event);

        $stats = null;
        $timezone = null;
        $defaultTeam = null;
        $commentForm = null;
        $attachments = [];
        $comments = null;
        $teams = null;
        $rates = [];
        $now = $this->getDateTimeFactory()->createDateTime();

        if ($this->isGranted('edit', $customer)) {
            if ($this->isGranted('create_team')) {
                $defaultTeam = $teamRepository->findOneBy(['name' => $customer->getName()]);
            }
            $rates = $rateRepository->getRatesForCustomer($customer);
        }

        if (null !== $customer->getTimezone()) {
            $timezone = new \DateTimeZone($customer->getTimezone());
        }

        if ($this->isGranted('budget', $customer) || $this->isGranted('time', $customer)) {
            $stats = $statisticService->getBudgetStatisticModel($customer, $now);
        }

        if ($this->isGranted('comments', $customer)) {
            $comments = $this->repository->getComments($customer);
        }

        if ($this->isGranted('comments_create', $customer)) {
            $commentForm = $this->getCommentForm($customer, new CustomerComment())->createView();
        }

        if ($this->isGranted('permissions', $customer) || $this->isGranted('details', $customer) || $this->isGranted('view_team')) {
            $teams = $customer->getTeams();
        }

        // additional boxes by plugins
        $event = new CustomerDetailControllerEvent($customer);
        $this->dispatcher->dispatch($event);
        $boxes = $event->getController();

        return $this->render('customer/details.html.twig', [
            'customer' => $customer,
            'comments' => $comments,
            'commentForm' => $commentForm,
            'attachments' => $attachments,
            'stats' => $stats,
            'team' => $defaultTeam,
            'teams' => $teams,
            'customer_now' => new \DateTime('now', $timezone),
            'rates' => $rates,
            'now' => $now,
            'boxes' => $boxes
        ]);
    }

    /**
     * @Route(path="/{id}/rate", name="admin_customer_rate_add", methods={"GET", "POST"})
     * @Security("is_granted('edit', customer)")
     */
    public function addRateAction(Customer $customer, Request $request, CustomerRateRepository $repository)
    {
        $rate = new CustomerRate();
        $rate->setCustomer($customer);

        $form = $this->createForm(CustomerRateForm::class, $rate, [
            'action' => $this->generateUrl('admin_customer_rate_add', ['id' => $customer->getId()]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $repository->saveRate($rate);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('customer_details', ['id' => $customer->getId()]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('customer/rates.html.twig', [
            'customer' => $customer,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route(path="/{id}/edit", name="admin_customer_edit", methods={"GET", "POST"})
     * @Security("is_granted('edit', customer)")
     */
    public function editAction(Customer $customer, Request $request)
    {
        return $this->renderCustomerForm($customer, $request);
    }

    /**
     * @Route(path="/{id}/delete", name="admin_customer_delete", methods={"GET", "POST"})
     * @Security("is_granted('delete', customer)")
     */
    public function deleteAction(Customer $customer, Request $request, CustomerStatisticService $statisticService)
    {
        $stats = $statisticService->getCustomerStatistics($customer);

        $deleteForm = $this->createFormBuilder(null, [
                'attr' => [
                    'data-form-event' => 'kimai.customerDelete',
                    'data-msg-success' => 'action.delete.success',
                    'data-msg-error' => 'action.delete.error',
                ]
            ])
            ->add('customer', CustomerType::class, [
                'query_builder_for_user' => true,
                'ignore_customer' => $customer,
                'required' => false,
            ])
            ->setAction($this->generateUrl('admin_customer_delete', ['id' => $customer->getId()]))
            ->setMethod('POST')
            ->getForm();

        $deleteForm->handleRequest($request);

        if ($deleteForm->isSubmitted() && $deleteForm->isValid()) {
            try {
                $this->repository->deleteCustomer($customer, $deleteForm->get('customer')->getData());
                $this->flashSuccess('action.delete.success');
            } catch (\Exception $ex) {
                $this->flashDeleteException($ex);
            }

            return $this->redirectToRoute('admin_customer');
        }

        return $this->render('customer/delete.html.twig', [
            'customer' => $customer,
            'stats' => $stats,
            'form' => $deleteForm->createView(),
        ]);
    }

    /**
     * @Route(path="/export", name="customer_export", methods={"GET"})
     */
    public function exportAction(Request $request, EntityWithMetaFieldsExporter $exporter)
    {
        $query = new CustomerQuery();
        $query->setCurrentUser($this->getUser());

        $form = $this->getToolbarForm($query);
        $form->setData($query);
        $form->submit($request->query->all(), false);

        if (!$form->isValid()) {
            $query->resetByFormError($form->getErrors());
        }

        $entries = $this->repository->getCustomersForQuery($query);

        $spreadsheet = $exporter->export(
            Customer::class,
            $entries,
            new CustomerMetaDisplayEvent($query, CustomerMetaDisplayEvent::EXPORT)
        );
        $writer = new BinaryFileResponseWriter(new XlsxWriter(), 'kimai-customers');

        return $writer->getFileResponse($spreadsheet);
    }

    /**
     * @param Customer $customer
     * @param Request $request
     * @return RedirectResponse|Response
     */
    private function renderCustomerForm(Customer $customer, Request $request)
    {
        $editForm = $this->createEditForm($customer);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $this->repository->saveCustomer($customer);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('customer_details', ['id' => $customer->getId()]);
            } catch (\Exception $ex) {
                $this->flashUpdateException($ex);
            }
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $editForm->createView()
        ]);
    }

    private function getToolbarForm(CustomerQuery $query): FormInterface
    {
        return $this->createForm(CustomerToolbarForm::class, $query, [
            'action' => $this->generateUrl('admin_customer', [
                'page' => $query->getPage(),
            ]),
            'method' => 'GET',
        ]);
    }

    private function getCommentForm(Customer $customer, CustomerComment $comment): FormInterface
    {
        if (null === $comment->getId()) {
            $comment->setCustomer($customer);
            $comment->setCreatedBy($this->getUser());
        }

        return $this->createForm(CustomerCommentForm::class, $comment, [
            'action' => $this->generateUrl('customer_comment_add', ['id' => $customer->getId()]),
            'method' => 'POST',
        ]);
    }

    private function createEditForm(Customer $customer): FormInterface
    {
        $event = new CustomerMetaDefinitionEvent($customer);
        $this->dispatcher->dispatch($event);

        if ($customer->getId() === null) {
            $url = $this->generateUrl('admin_customer_create');
        } else {
            $url = $this->generateUrl('admin_customer_edit', ['id' => $customer->getId()]);
        }

        return $this->createForm(CustomerEditForm::class, $customer, [
            'action' => $url,
            'method' => 'POST',
            'include_budget' => $this->isGranted('budget', $customer),
            'include_time' => $this->isGranted('time', $customer),
        ]);
    }
}
