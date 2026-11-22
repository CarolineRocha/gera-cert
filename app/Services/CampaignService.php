<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Template;

class CampaignService
{
    public function process(Project $project)
    {
        $participants = $project->participants()->get();

        $certificateTemplate = Template::where(
            'type',
            'certificate'
        )->first();

        $emailTemplate = Template::where(
            'type',
            'email'
        )->first();

        foreach ($participants as $participant) {

            try {

                var_dump('gera certificado e salva no banco');
                // gerar certificado
                var_dump('enviar email e salva log em banco');

                // enviar email

            } catch (\Exception $e) {

                var_dump('salvar log erro');

                // salvar log erro
            }
        }
    }
}
