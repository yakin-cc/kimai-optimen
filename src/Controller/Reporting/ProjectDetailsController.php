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
use App\Reporting\ProjectDetails\UserDetailsForm;
use App\Reporting\ProjectDetails\UserDetailsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Reporting\ProjectDetails\MonthlyDetailsForm;
use App\Reporting\ProjectDetails\ProjectDetailsForm;
use App\Reporting\ProjectDetails\ActivityDetailsForm;
use App\Reporting\ProjectDetails\MonthlyDetailsQuery;
use App\Reporting\ProjectDetails\ProjectDetailsQuery;
use App\Reporting\ProjectDetails\ActivityDetailsQuery;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

final class ProjectDetailsController extends AbstractController
{
    /**
     * @Route(path="/reporting/project_details", name="report_project_details", methods={"GET", "POST"})
     * @Security("is_granted('view_reporting') and is_granted('details', 'project')")
     */
    public function __invoke(Request $request, ProjectStatisticService $service)
    {   
        $dateFactory = $this->getDateTimeFactory();
        $user = $this->getUser();
        $projectView = null;
        $projectDetails = null;

        $query = new ProjectDetailsQuery($dateFactory->createDateTime(), $user);
        $form = $this->createForm(ProjectDetailsForm::class, $query, ['attr' => ['name' => 'project_details_form'], 'user' => $user]);
        $form->submit($request->query->all(), false);

        if ($query->getProject() !== null && $this->isGranted('details', $query->getProject())) {
            $projectViews = $service->getProjectView($user, [$query->getProject()], $query->getToday());
            $projectView = $projectViews[0];
            $projectDetails = $service->getProjectsDetails($query);
        }

        if ($query->getMonth() !== null && $this->isGranted('details', $query->getProject())){
            $selectedMonth = $query ->getMonth();
            $projectViews = $service->getProjectView($user, [$query->getProject()], $query->getToday(), true, $selectedMonth);
            $projectView = $projectViews[0];
        }

        if ($query->getActivity() !== null && $this->isGranted('details', $query->getProject())){
            $selectedActivity = $query->getActivity();
        }

        if($query->getSelectedUser() !== null && $this->isGranted('details', $query->getProject())){
            $selectedUser = $query->getSelectedUser();
        }

        return $this->render('reporting/project_details.html.twig', [
            'project' => $query->getProject(),
            'project_view' => $projectView,
            'project_details' => $projectDetails,
            'form' => $form->createView(),
            'now' => $this->getDateTimeFactory()->createDateTime(),
        ]);
    }
}