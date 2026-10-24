<?php

namespace Tests\Feature;

use App\i18n\Translator;
use App\i18n\UiLocale;
use App\Models\Report;
use App\Models\Response;
use Tests\TestCase;

class ReportLocalePrivacyTest extends TestCase
{
    public function test_shared_matrix_cache_keeps_admin_names_out_of_anonymous_public_responses(): void
    {
        $poll = $this->createPoll(['status' => 'open', 'visibility' => 'anonymous', 'locale' => 'fr']);
        $question = $this->createQuestion($poll->id);
        Response::create($poll->id, ['voter_name' => 'Private Person']);
        $report = Report::create(['question_id' => $question->id, 'report_type' => 'response_matrix', 'is_public' => true]);
        UiLocale::initialize('de');
        $adminUrl = "/api/polls/{$poll->publicId}/admin/{$poll->adminToken}/reports";
        $publicUrl = "/api/polls/{$poll->publicId}/reports";

        // Warm the same shared cache in each audience order.
        foreach ([true, false] as $adminFirst) {
            Report::find($report->id)->invalidateCache();
            $this->assertSuccess($this->callApi('GET', $adminFirst ? $adminUrl : $publicUrl));
            $admin = $this->callApi('GET', $adminUrl);
            $public = $this->callApi('GET', $publicUrl);
            $this->assertSuccess($admin);
            $this->assertSuccess($public);
            $this->assertSame('Private Person', $admin['reports'][0]['cached_result']['rows'][0]['voter']);
            $this->assertStringNotContainsString('Private Person', json_encode($public));
            $this->assertSame(Translator::translateForLocale('fr', 'report_voter_number', ['number' => 1]), $public['reports'][0]['cached_result']['rows'][0]['voter']);
            $this->assertFalse($public['reports'][0]['cached_result']['show_names']);
            $this->assertSame('de', Translator::getLocale());
        }

        $poll->update(['visibility' => 'full']);
        $public = $this->callApi('GET', $publicUrl);
        $this->assertSame('Private Person', $public['reports'][0]['cached_result']['rows'][0]['voter']);
    }
}
