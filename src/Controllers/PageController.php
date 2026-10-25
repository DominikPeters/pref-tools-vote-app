<?php

namespace App\Controllers;

use App\Auth;
use App\Models\Poll;
use App\Models\SiteSetting;

class PageController
{
    /**
     * GET / - Landing page
     */
    public function home(array $params): void
    {
        $context = \App\Services\MarketingLocale::context();
        if ($context !== null && $context['locale'] !== null) {
            \App\Services\MarketingLocale::activate($context['locale']);
        }
        view('home', [
            'user' => Auth::getInstance()->user(),
            'marketingPage' => 'home',
        ]);
    }

    /**
     * GET /demo - Demo poll voting page
     */
    public function demo(array $params): void
    {
        $demoPollId = SiteSetting::get('demo.poll_id');

        if (empty($demoPollId)) {
            view('error', [
                'title' => __('page_demo_not_configured'),
                'message' => __('page_no_demo_poll_has_been_configured_please_check_back_later_or_contact_the_site_a'),
            ]);
            return;
        }

        $poll = Poll::findByPublicId($demoPollId);

        if (!$poll) {
            view('error', [
                'title' => __('page_demo_not_available'),
                'message' => __('page_the_demo_poll_could_not_be_found_it_may_have_been_removed'),
            ]);
            return;
        }

        // Reuse the poll method logic with the demo poll
        $params['publicId'] = $demoPollId;
        $this->poll($params);
    }

    /**
     * GET /demo/results - Demo poll results page
     */
    public function demoResults(array $params): void
    {
        $demoPollId = SiteSetting::get('demo.poll_id');

        if (empty($demoPollId)) {
            view('error', [
                'title' => __('page_demo_not_configured'),
                'message' => __('page_no_demo_poll_has_been_configured_please_check_back_later_or_contact_the_site_a'),
            ]);
            return;
        }

        $poll = Poll::findByPublicId($demoPollId);

        if (!$poll) {
            view('error', [
                'title' => __('page_demo_not_available'),
                'message' => __('page_the_demo_poll_could_not_be_found_it_may_have_been_removed'),
            ]);
            return;
        }

        // Reuse the results method logic with the demo poll
        $params['publicId'] = $demoPollId;
        $this->results($params);
    }

    /**
     * GET /create - Form builder (new poll)
     * GET /:publicId/admin/:adminToken/edit - Form builder (edit existing)
     */
    public function builder(array $params): void
    {
        $poll = null;
        $adminToken = null;

        // Check if editing an existing poll
        if (!empty($params['publicId']) && !empty($params['adminToken'])) {
            $poll = Poll::findByPublicId($params['publicId']);

            if (!$poll) {
                http_response_code(404);
                view('error', [
                    'title' => __('page_poll_not_found'),
                    'message' => __('page_the_poll_you_are_looking_for_does_not_exist'),
                ]);
                return;
            }

            if (!$poll->verifyAdminToken($params['adminToken'])) {
                http_response_code(403);
                view('error', [
                    'title' => __('page_access_denied'),
                    'message' => __('page_invalid_admin_token'),
                ]);
                return;
            }

            $poll->loadQuestions();
            $adminToken = $params['adminToken'];
        }

        view('builder', [
            'user' => Auth::getInstance()->user(),
            'poll' => $poll,
            'adminToken' => $adminToken,
        ]);
    }

    /**
     * POST /preview - Preview poll without saving to database
     */
    public function preview(array $params): void
    {
        // Read data from form POST (JSON string in 'data' field)
        $jsonString = $_POST['data'] ?? null;

        if (!$jsonString) {
            // Also try reading raw JSON body
            $jsonString = file_get_contents('php://input');
        }

        $data = json_decode($jsonString, true);

        if (!$data) {
            http_response_code(400);
            view('error', [
                'title' => __('page_invalid_request'),
                'message' => __('page_invalid_json_data_provided'),
            ]);
            return;
        }

        // Build Poll object without saving
        $poll = new Poll();
        $poll->publicId = 'preview';
        $poll->title = $data['title'] ?? __('untitled_poll');
        $poll->description = $data['description'] ?? null;
        $poll->status = 'open';
        $poll->visibility = 'private';
        $poll->collectName = $data['collect_name'] ?? false;
        $poll->allowEditOwn = $data['allow_edit_own'] ?? true;
        $poll->allowEditAny = $data['allow_edit_any'] ?? false;
        $poll->randomizeOptions = $data['randomize_options'] ?? false;
        $poll->votingMode = $data['voting_mode'] ?? 'open';
        $poll->thankYouMessage = $data['thank_you_message'] ?? null;

        // Build Question objects
        $questionIdCounter = 1;
        $optionIdCounter = 1;

        foreach ($data['questions'] ?? [] as $index => $qData) {
            $question = new \App\Models\Question();
            $question->id = $questionIdCounter++;
            $question->pollId = 0;
            $question->sortOrder = $qData['sort_order'] ?? $index;
            $question->type = $qData['type'] ?? 'single_choice';
            $question->text = $qData['text'] ?? '';
            $question->description = $qData['description'] ?? null;
            $question->required = $qData['required'] ?? true;
            $question->settings = $qData['settings'] ?? null;

            // Build Option objects
            foreach ($qData['options'] ?? [] as $oIndex => $oData) {
                $option = new \App\Models\Option();
                $option->id = $optionIdCounter++;
                $option->questionId = $question->id;
                $option->sortOrder = $oData['sort_order'] ?? $oIndex;
                $option->label = $oData['label'] ?? '';
                $option->description = $oData['description'] ?? null;
                $option->features = $oData['features'] ?? null;
                $question->options[] = $option;
            }

            $poll->questions[] = $question;
        }

        view('poll', [
            'poll' => $poll,
            'user' => Auth::getInstance()->user(),
            'existingResponse' => null,
            'hasVoted' => false,
            'isPreview' => true,
        ]);
    }

    /**
     * GET /login - Login/register page
     */
    public function login(array $params): void
    {
        // Handle email verification token in PHP to avoid "flash" of login page
        $verifyToken = $_GET['verify_token'] ?? null;
        if ($verifyToken) {
            $user = \App\Models\User::findByVerificationToken($verifyToken);
            if ($user) {
                $user->markEmailVerified();
                \App\Services\LogService::getInstance()->log('user.email_verified', null, $user->id);

                $auth = Auth::getInstance();
                if (!$auth->check()) {
                    $auth->login($user);
                }

                \App\Router::redirect(url('dashboard?verified=1'));
                return;
            }
        }

        // Redirect if already logged in
        if (Auth::getInstance()->check()) {
            \App\Router::redirect(url('dashboard'));
            return;
        }

        view('login', []);
    }

    /**
     * GET /dashboard - User dashboard
     */
    public function dashboard(array $params): void
    {
        $user = Auth::getInstance()->user();

        if (!$user) {
            \App\Router::redirect(url('login'));
            return;
        }

        $polls = Poll::findByUserId($user->id);
        $votedPolls = Poll::findVotedByUserId($user->id);

        view('dashboard', [
            'user' => $user,
            'polls' => $polls,
            'votedPolls' => $votedPolls,
        ]);
    }

    /**
     * GET /:publicId - Voter form
     */
    public function poll(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => __('page_poll_not_found'),
                'message' => __('page_the_poll_you_are_looking_for_does_not_exist'),
            ]);
            return;
        }

        \App\i18n\Translator::setLocale($poll->locale);

        // Voter pages can contain session-specific access credentials.
        header('Cache-Control: no-store');

        // If vote is closed and results are public, redirect to results
        if ($poll->status === 'closed' && $poll->visibility !== 'private') {
            header('Location: ' . url("{$poll->publicId}/results"));
            if (!defined('PHPUNIT_RUNNING')) {
                exit;
            }
            return;
        }

        // Check password protection
        if ($poll->accessMode === 'password' && $poll->accessPassword) {
            $sessionKey = 'poll_access_' . $poll->publicId;

            // Handle password submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_password'])) {
                if ($poll->verifyAccessPassword($_POST['access_password'])) {
                    $_SESSION[$sessionKey] = true;
                    header('Location: ' . url($poll->publicId));
                    if (!defined('PHPUNIT_RUNNING')) {
                        exit;
                    }
                    return;
                } else {
                    view('password', [
                        'poll' => $poll,
                        'error' => __('page_incorrect_password_please_try_again'),
                    ]);
                    return;
                }
            }

            // Check if already unlocked
            if (empty($_SESSION[$sessionKey])) {
                view('password', [
                    'poll' => $poll,
                    'error' => null,
                ]);
                return;
            }
        }

        // New links exchange fragment credentials through POST. Accept old query
        // links too, but redirect before rendering assets or participant content.
        $token = $_POST['access_token'] ?? $_GET['token'] ?? null;
        if ($token !== null) {
            $accessToken = is_string($token) && $token !== ''
                ? \App\Models\AccessToken::findByToken($poll->id, $token) : null;
            $emailInvite = is_string($token) && $token !== '' && !$accessToken
                ? \App\Models\EmailInvitation::findByToken($poll->id, $token) : null;
            $credential = $accessToken ?? $emailInvite;

            if (!$credential || $credential->isUsed()) {
                http_response_code(403);
                view('poll-access', [
                    'poll' => $poll,
                    'user' => Auth::getInstance()->user(),
                    'accessTitle' => $credential
                        ? __($emailInvite ? 'page_invitation_used' : 'page_access_link_used')
                        : __('page_access_denied'),
                    'accessMessage' => $credential
                        ? __($emailInvite ? 'page_this_invitation_link_has_already_been_used' : 'page_this_access_link_has_already_been_used')
                        : __('error_invalid_access_token'),
                ]);
                return;
            }

            $_SESSION['poll_token_' . $poll->publicId] = $token;
            header('Location: ' . url($poll->publicId), true, 303);
            if (!defined('PHPUNIT_RUNNING')) {
                exit;
            }
            return;
        }

        // Enforce token-based access if required by voting mode or access mode
        $requiresToken = $poll->requiresIdentity() || $poll->accessMode === 'token';
        
        if ($requiresToken) {
            if (empty($_SESSION['poll_token_' . $poll->publicId])) {
                http_response_code(403);
                view('poll-access', [
                    'poll' => $poll,
                    'user' => Auth::getInstance()->user(),
                ]);
                return;
            }
        }

        $poll->loadQuestions();

        // Check for existing response
        $existingResponse = null;
        $hasVoted = false;
        $voterToken = $_COOKIE['voter_token_' . $poll->publicId] ?? null;
        if ($voterToken) {
            $existingResponse = \App\Models\Response::findByVoterToken($poll->id, $voterToken);
            if ($existingResponse) {
                $hasVoted = true;
                // Only load answers if they can actually edit
                if ($poll->allowEditOwn || $poll->allowEditAny) {
                    $existingResponse->loadAnswers();
                } else {
                    $existingResponse = null; // Don't pass response if not editing
                }
            }
        }

        view('poll', [
            'poll' => $poll,
            'user' => Auth::getInstance()->user(),
            'existingResponse' => $existingResponse,
            'hasVoted' => $hasVoted,
        ]);
    }

    /**
     * GET /:publicId/results - Results page
     */
    public function results(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => __('page_poll_not_found'),
                'message' => __('page_the_poll_you_are_looking_for_does_not_exist'),
            ]);
            return;
        }

        // Check if results are visible
        \App\i18n\Translator::setLocale($poll->locale);
        if ($poll->visibility === 'private') {
            http_response_code(403);
            view('error', [
                'title' => __('page_results_not_available'),
                'message' => __('page_the_results_for_this_poll_are_not_yet_available'),
            ]);
            return;
        }

        $poll->loadQuestions();

        view('results', [
            'poll' => $poll,
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /:publicId/admin/:adminToken - Admin panel
     */
    public function admin(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => __('page_poll_not_found'),
                'message' => __('page_the_poll_you_are_looking_for_does_not_exist'),
            ]);
            return;
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            http_response_code(403);
            view('error', [
                'title' => __('page_access_denied'),
                'message' => __('page_invalid_admin_token'),
            ]);
            return;
        }

        $poll->loadQuestions();

        view('admin', [
            'poll' => $poll,
            'adminToken' => $params['adminToken'],
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /:publicId/admin/:adminToken/results - Admin results page
     */
    public function resultsAdmin(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => __('page_poll_not_found'),
                'message' => __('page_the_poll_you_are_looking_for_does_not_exist'),
            ]);
            return;
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            http_response_code(403);
            view('error', [
                'title' => __('page_access_denied'),
                'message' => __('page_invalid_admin_token'),
            ]);
            return;
        }

        $poll->loadQuestions();

        view('results-admin', [
            'poll' => $poll,
            'adminToken' => $params['adminToken'],
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /:publicId/admin/:adminToken/responses - Individual responses browser
     */
    public function responsesAdmin(array $params): void
    {
        $poll = Poll::findByPublicId($params['publicId']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => __('page_poll_not_found'),
                'message' => __('page_the_poll_you_are_looking_for_does_not_exist'),
            ]);
            return;
        }

        if (!$poll->verifyAdminToken($params['adminToken'])) {
            http_response_code(403);
            view('error', [
                'title' => __('page_access_denied'),
                'message' => __('page_invalid_admin_token'),
            ]);
            return;
        }

        $poll->loadQuestions();

        view('responses-admin', [
            'poll' => $poll,
            'adminToken' => $params['adminToken'],
            'user' => Auth::getInstance()->user(),
        ]);
    }

    /**
     * GET /about - About page
     */
    public function about(array $params): void
    {
        $context = \App\Services\MarketingLocale::context();
        if ($context !== null && $context['locale'] !== null) {
            \App\Services\MarketingLocale::activate($context['locale']);
        }
        view('about', [
            'user' => Auth::getInstance()->user(),
            'marketingPage' => 'about',
        ]);
    }

    /**
     * GET /privacy - Privacy policy page
     */
    public function privacy(array $params): void
    {
        $this->renderDocument('PRIVACY_POLICY.md', __('privacy_policy'), __('page_privacy_policy_not_found'), __('page_the_privacy_policy_document_is_not_available'));
    }

    /**
     * GET /terms - Terms of use page
     */
    public function terms(array $params): void
    {
        $this->renderDocument('TERMS_OF_USE.md', __('terms_of_use'), __('page_document_not_found'), __('page_document_not_available'));
    }

    /**
     * GET /legal - Legal notice page
     */
    public function legalNotice(array $params): void
    {
        $this->renderDocument('LEGAL_NOTICE.md', __('legal_notice'), __('page_document_not_found'), __('page_document_not_available'));
    }

    /**
     * Render an English Markdown document from the project root
     */
    private function renderDocument(string $fileName, string $title, string $notFoundTitle, string $notFoundMessage): void
    {
        $filePath = __DIR__ . '/../../' . $fileName;

        if (!file_exists($filePath)) {
            http_response_code(404);
            view('error', [
                'title' => $notFoundTitle,
                'message' => $notFoundMessage,
            ]);
            return;
        }

        $markdown = file_get_contents($filePath);

        require_once __DIR__ . '/../../lib/Parsedown/Parsedown.php';
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);
        $parsedown->setBreaksEnabled(true);
        $html = $parsedown->text($markdown);

        view('document', [
            'user' => Auth::getInstance()->user(),
            'documentTitle' => $title,
            'content' => $html,
        ]);
    }

    /**
     * GET /:publicId/embed/:embedToken - Embedded poll page
     */
    public function embed(array $params): void
    {
        $poll = Poll::findByEmbedToken($params['publicId'], $params['embedToken']);

        if (!$poll) {
            http_response_code(404);
            view('error', [
                'title' => __('page_poll_not_found'),
                'message' => __('page_the_poll_you_are_looking_for_does_not_exist_or_the_embed_link_is_invalid'),
            ]);
            return;
        }

        \App\i18n\Translator::setLocale($poll->locale);
        if (!$poll->canBeEmbedded()) {
            http_response_code(403);
            $message = __('page_this_poll_cannot_be_embedded');
            if ($poll->votingMode !== 'open') {
                $message = __('page_only_polls_with_open_voting_mode_can_be_embedded');
            } elseif ($poll->status !== 'open') {
                $message = __('page_this_poll_is_not_currently_open_for_voting');
            } elseif (!$poll->allowEmbedding) {
                $message = __('page_embedding_is_not_enabled_for_this_poll');
            }
            view('error', [
                'title' => __('page_embedding_not_available'),
                'message' => $message,
            ]);
            return;
        }

        $poll->loadQuestions();

        view('embed', [
            'poll' => $poll,
            'embedToken' => $params['embedToken'],
            'isPreview' => isset($_GET['preview']),
        ]);
    }
}
