<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Reporting;

use App\Entity\Project;
use App\Controller\AbstractController;
use App\Project\ProjectStatisticService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Reporting\ProjectDetails\ProjectDetailsForm;
use App\Reporting\ProjectDetails\ProjectDetailsQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Security as SecurityHelper;

final class ProjectDetailsController extends AbstractController
{
    private $projectStatisticService;
    private $securityHelper;
    private $formFactory;

    public function __construct(ProjectStatisticService $projectStatisticService, SecurityHelper $securityHelper, FormFactoryInterface $formFactory)
    {
        $this->projectStatisticService = $projectStatisticService;
        $this->securityHelper = $securityHelper;
        $this->formFactory = $formFactory;
    }

    /**
     * @Route(path="/reporting/project_details", name="report_project_details", methods={"GET", "POST"})
     * @Security("is_granted('view_reporting') and is_granted('details', 'project')")
     */
    public function __invoke(Request $request)
    {
        $user = $this->getUser();
        $dateFactory = $this->getDateTimeFactory();
        $query = new ProjectDetailsQuery($dateFactory->createDateTime(), $user);
        
        $form = $this->createProjectDetailsForm($request, $query, $user);
        
        $projectView = null;
        $projectDetails = null;

        if ($this->canViewProjectDetails($query)) {
            $projectView = $this->getProjectView($query, $user);
            $projectDetails = $this->getProjectDetails($query);
        }

        return $this->render('reporting/project_details.html.twig', [
            'project' => $query->getProject(),
            'project_view' => $projectView,
            'project_details' => $projectDetails,
            'selectedMonth' => $query->getMonth(),
            'selectedUser' => $query->getSelectedUser(),
            'selectedActivity' => $query->getActivity(),
            'form' => $form->createView(),
            'now' => $this->getDateTimeFactory()->createDateTime(),
        ]);
    }

    private function createProjectDetailsForm(Request $request, ProjectDetailsQuery $query, $user)
    {
        $form = $this->formFactory->create(ProjectDetailsForm::class, $query, [
            'attr' => ['name' => 'project_details_form'], 
            'user' => $user
        ]);
        $form->submit($request->query->all(), false);

        return $form;
    }

    private function canViewProjectDetails(ProjectDetailsQuery $query)
    {
        return $query->getProject() !== null && $this->securityHelper->isGranted('details', $query->getProject());
    }

    private function getProjectView(ProjectDetailsQuery $query, $user)
    {
        $projectViews = $this->projectStatisticService->getProjectView(
            $user, 
            [$query->getProject()], 
            $query->getToday(), 
            $query->getMonth()
        );

        return $projectViews[0] ?? null;
    }

    private function getProjectDetails(ProjectDetailsQuery $query)
    {
        return $this->projectStatisticService->getProjectsDetails(
            $query, 
            $query->getProject()->getId(), 
            $query->getMonth(), 
            $query->getSelectedUser(), 
            $query->getActivity()
        );
    }
}