<?php

namespace Tests\Feature\Docs;

use Tests\TestCase;

class ColonyTemplateDocumentationSmokeTest extends TestCase
{
    public function test_colony_template_reference_doc_exists_and_is_non_empty(): void
    {
        $path = base_path('site/docs/content/reference/colony-template.md');

        $this->assertFileExists($path);
        $this->assertNotEmpty(file_get_contents($path));
    }

    public function test_farming_explanation_doc_exists_and_is_non_empty(): void
    {
        $path = base_path('site/docs/content/referees/explanation/colony-template-farming.md');

        $this->assertFileExists($path);
        $this->assertNotEmpty(file_get_contents($path));
    }

    public function test_factories_explanation_doc_exists_and_is_non_empty(): void
    {
        $path = base_path('site/docs/content/referees/explanation/colony-template-factories.md');

        $this->assertFileExists($path);
        $this->assertNotEmpty(file_get_contents($path));
    }

    public function test_mining_explanation_doc_exists_and_is_non_empty(): void
    {
        $path = base_path('site/docs/content/referees/explanation/colony-template-mining.md');

        $this->assertFileExists($path);
        $this->assertNotEmpty(file_get_contents($path));
    }
}
