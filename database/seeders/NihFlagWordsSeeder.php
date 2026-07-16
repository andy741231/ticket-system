<?php

namespace Database\Seeders;

use App\Models\DocumentFlagWord;
use Illuminate\Database\Seeder;

/**
 * Seeds the document_flag_words table with the 235 NIH flagged terms
 * (compiled from Nature's reporting on the NIH text-analysis screening tool)
 * along with suggested replacement language for each term.
 *
 * Sources:
 *  - https://www.nature.com/articles/d41586-026-01924-8
 *  - https://github.com/stephenturner/nih-flagged-words
 *  - https://www.notus.org/health-science/nih-funding-grants-review-terms-text-analysis-tool-health-equity-racism
 *  - https://thisweekinpublichealth.com/blog/2025/02/04/some-linguistics-suggestions-for-continuing-the-work/
 *
 * This seeder OVERWRITES the existing flag word list. Because document_flags
 * has an ON DELETE CASCADE foreign key to document_flag_words, running this
 * seeder will clear existing per-document flag associations; rescan documents
 * afterwards to rebuild them.
 */
class NihFlagWordsSeeder extends Seeder
{
    public function run(): void
    {
        // Overwrite: remove all existing flag words (cascades to document_flags)
        DocumentFlagWord::query()->delete();

        $now = now();
        $rows = [];
        foreach (self::flagWords() as [$word, $replacement]) {
            $rows[] = [
                'word' => $word,
                'suggested_replacement' => $replacement,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert in chunks to avoid binding limits
        foreach (array_chunk($rows, 100) as $chunk) {
            DocumentFlagWord::insert($chunk);
        }
    }

    /**
     * @return array<int, array{0:string,1:string}>  [word, suggested_replacement]
     */
    public static function flagWords(): array
    {
        return [
            // ===== Climate Change =====
            ['changing climate', 'environmental shifts / long-term environmental trends'],
            ['climate change', 'environmental conditions / long-term environmental patterns'],
            ['climate crisis', 'environmental conditions (avoid crisis framing)'],
            ['climate justice', 'environmental policy / environmental risk management'],
            ['environmental justice', 'environmental risk distribution / community environmental exposure'],
            ['fossil fuel', 'traditional energy sources / carbon-based energy'],
            ['global warming', 'temperature trends / long-term temperature patterns'],

            // ===== DEI: acronyms & general =====
            ['DEI', '(omit; reframe around measurable outcomes)'],
            ['DEIA', '(omit; reframe around measurable outcomes)'],
            ['DEIB', '(omit; reframe around measurable outcomes)'],
            ['DEIJ', '(omit; reframe around measurable outcomes)'],
            ['PEDP', '(spell out or reframe as program objectives)'],
            ['RPED', '(spell out or reframe as program objectives)'],
            ['HBCU', 'minority-serving institution / specific institution name'],
            ['HSI', 'minority-serving institution / specific institution name'],
            ['URM', 'population with lower participation rates'],

            // ===== DEI: "diversity" + modifier =====
            ['address diversity', 'address participation gaps'],
            ['advance diversity', 'advance participation'],
            ['advancement diversity', 'participation advancement'],
            ['advances diversity', 'participation advances'],
            ['advancing diversity', 'advancing participation'],
            ['attendee diversity', 'varied attendance / participant composition'],
            ['bolster diversity', 'bolster participation'],
            ['broaden diversity', 'broaden participation'],
            ['broadening diversity', 'broadening participation'],
            ['broadens diversity', 'broadens participation'],
            ['campus diversity', 'varied campus composition'],
            ['center diversity', 'center participation'],
            ['cohort diversity', 'varied cohort composition'],
            ['commitment diversity', 'commitment to participation'],
            ['committed diversity', 'committed to participation'],
            ['cultural diversity', 'cultural variation / cross-cultural composition'],
            ['diverse applicant', 'applicants from varied backgrounds'],
            ['diverse audience', 'varied audience'],
            ['diverse background', 'varied background'],
            ['diverse biologist', 'biologists from varied backgrounds'],
            ['diverse candidate', 'candidates from varied backgrounds'],
            ['diverse chemist', 'chemists from varied backgrounds'],
            ['diverse cohort', 'varied cohort'],
            ['diverse collaborator', 'collaborators from varied institutions'],
            ['diverse communities', 'varied communities / multiple communities'],
            ['diverse cultur', 'cross-cultural / varied cultural backgrounds'],
            ['diverse faculty', 'faculty from varied backgrounds'],
            ['diverse graduate', 'graduate students from varied backgrounds'],
            ['diverse inclusive', 'broad participation'],
            ['diverse individual', 'individuals from varied backgrounds'],
            ['diverse investigator', 'investigators from varied backgrounds'],
            ['diverse leader', 'leaders from varied backgrounds'],
            ['diverse learner', 'learners from varied backgrounds'],
            ['diverse people', 'individuals from varied backgrounds'],
            ['diverse personnel', 'personnel from varied backgrounds'],
            ['diverse postdoc', 'postdocs from varied backgrounds'],
            ['diverse predoc', 'predocs from varied backgrounds'],
            ['diverse researcher', 'researchers from varied backgrounds'],
            ['diverse respondent', 'respondents from varied backgrounds'],
            ['diverse scholar', 'scholars from varied backgrounds'],
            ['diverse scientist', 'scientists from varied backgrounds'],
            ['diverse stem', 'broad STEM participation'],
            ['diverse student', 'students from varied backgrounds'],
            ['diverse talented', 'talented individuals from varied backgrounds'],
            ['diverse team', 'multidisciplinary team / team from varied institutions'],
            ['diverse trainee', 'trainees from varied backgrounds'],
            ['diverse undergraduate', 'undergraduates from varied backgrounds'],
            ['diverse volunteer', 'volunteers from varied backgrounds'],
            ['diverse workforce', 'expanded workforce / workforce from varied backgrounds'],
            ['diversification workforce', 'workforce expansion'],
            ['diversify workforce', 'expand the workforce'],
            ['diversity inclusion', 'broad participation'],
            ['diversity leader', 'participation coordinator'],
            ['diversity program', 'participation program / workforce development program'],
            ['diversity recruit', 'participation recruitment'],
            ['diversity stem', 'STEM participation'],
            ['diversity supplement', 'participation supplement / training supplement'],
            ['embrace diversity', 'embrace broad participation'],
            ['embraces diversity', 'embraces broad participation'],
            ['embracing diversity', 'embracing broad participation'],
            ['encourage diversity', 'encourage broad participation'],
            ['encouraged diversity', 'encouraged broad participation'],
            ['encourages diversity', 'encourages broad participation'],
            ['encouraging diversity', 'encouraging broad participation'],
            ['engage diverse', 'engage varied participants'],
            ['engage diversity', 'engage broad participation'],
            ['engagement diverse', 'engagement of varied participants'],
            ['engagement diversity', 'engagement in broad participation'],
            ['engaging diverse', 'engaging varied participants'],
            ['engaging diversity', 'engaging broad participation'],
            ['enhance diversity', 'enhance participation'],
            ['enhanced diversity', 'enhanced participation'],
            ['enhances diversity', 'enhances participation'],
            ['enhancing diversity', 'enhancing participation'],
            ['ethnic diversity', 'varied ethnic composition / multi-ethnic composition'],
            ['ethnic segregation', 'separation by ethnic group / spatial separation by ethnicity'],
            ['ethnically diverse', 'multi-ethnic'],
            ['expand diversity', 'expand participation'],
            ['expanding diversity', 'expanding participation'],
            ['expands diversity', 'expands participation'],
            ['faculty diversity', 'varied faculty composition'],
            ['graduate diversity', 'varied graduate composition'],
            ['graduates diversity', 'varied graduate composition'],
            ['improve diversity', 'improve participation'],
            ['improved diversity', 'improved participation'],
            ['improves diversity', 'improves participation'],
            ['improving diversity', 'improving participation'],
            ['inclusion diversity', 'broad participation'],
            ['increase diversity', 'increase participation'],
            ['increased diversity', 'increased participation'],
            ['increases diversity', 'increases participation'],
            ['increasing diversity', 'increasing participation'],
            ['increasingly diverse', 'increasingly varied'],
            ['lack diversity', 'limited participation / participation gaps'],
            ['leadership diversity', 'varied leadership composition'],
            ['leading diversity', 'leading participation efforts'],
            ['member diversity', 'varied membership'],
            ['members diversity', 'varied membership'],
            ['monitor diversity', 'monitor participation'],
            ['need diversity', 'participation needs'],
            ['office diversity', 'participation office (or omit)'],
            ['openness diversity', 'openness to varied participation'],
            ['postdoc diversity', 'varied postdoc composition'],
            ['postdoctoral diversity', 'varied postdoc composition'],
            ['program diversity', 'varied program composition'],
            ['progress diversity', 'participation progress'],
            ['promote diversity', 'promote broad participation'],
            ['promotes diversity', 'promotes broad participation'],
            ['promoting diversity', 'promoting broad participation'],
            ['promotion diversity', 'participation promotion'],
            ['recruitment diversity', 'varied recruitment / expanded recruitment'],
            ['scientists diversity', 'varied scientist composition'],
            ['staff diversity', 'varied staff composition'],
            ['stem diversity', 'STEM participation'],
            ['student diversity', 'varied student composition'],
            ['students diversity', 'varied student composition'],
            ['support diversity', 'support broad participation'],
            ['supported diversity', 'supported participation'],
            ['supporting diversity', 'supporting participation'],
            ['supportive diversity', 'supportive of participation'],
            ['supports diversity', 'supports participation'],
            ['talent diversity', 'varied talent pool'],
            ['train diverse', 'train varied participants'],
            ['training diverse', 'training varied participants'],
            ['user diversity', 'varied user base'],
            ['values diversity', 'values broad participation'],
            ['workforce diversification', 'workforce expansion'],
            ['workforce diversity', 'expanded workforce composition'],

            // ===== DEI: equity / inequality / justice =====
            ['cultural equity', 'fair cultural resource allocation'],
            ['digital equity', 'equal technology access / broadband access gaps'],
            ['equit', 'fair resource allocation / equal access (all forms)'],
            ['ethnic equit', 'fair allocation across ethnic groups'],
            ['health equit', 'health access / fair health resource allocation / addressing unmet health needs'],
            ['health inequit', 'differences in health outcomes / health outcome variability'],
            ['inequit', 'differences in outcomes / variability (all forms)'],
            ['justice', 'fair access / equitable process (or omit, context-dependent)'],
            ['criminal justice', 'correctional system / legal system'],
            ['racial equit', 'fair allocation across racial groups'],

            // ===== DEI: race / ethnicity =====
            ['apartheid', 'institutionalized racial separation (historical)'],
            ['critical race theory', '(omit; reframe around specific research questions)'],
            ['disadvantaged minorit', 'populations with lower socioeconomic participation'],
            ['hispanic serving', 'specific institution name / minority-serving institution'],
            ['historically black', 'specific institution name'],
            ['latinx', 'Latino / Hispanic (or specific population descriptor)'],
            ['minorit', 'population subgroup / specific group name (all forms)'],
            ['oppression', '(omit; reframe around measurable conditions)'],
            ['racial discrimination', 'differential treatment by race (measurable)'],
            ['racial diversity', 'varied racial composition / multi-racial composition'],
            ['racial segregation', 'residential separation by race / spatial separation'],
            ['racially diverse', 'multi-racial'],
            ['racism', '(omit; reframe as differential treatment or measured bias)'],
            ['racist', '(omit; reframe around specific measured behaviors)'],
            ['residential segregation', 'residential separation / spatial isolation'],
            ['structural racism', 'system-level factors affecting health outcomes / institutional policies'],
            ['systemic racism', 'system-level factors / institutional policies'],
            ['underrepresent', 'lower participation rates / reduced representation (all forms)'],
            ['underrepresented group', 'population with lower participation rates'],
            ['underrepresented minorit', 'population subgroup with lower participation'],
            ['underrepresented population', 'population with lower participation rates'],
            ['underrepresented student', 'students from lower-participation populations'],

            // ===== DEI: other =====
            ['inclusiv', 'broad participation / open to all eligible participants (all forms)'],
            ['recruitment plan', 'staffing plan / talent acquisition plan'],

            // ===== Gender =====
            ['assigned birth', '(omit; use biological sex per administration policy)'],
            ['assigned female birth', 'female'],
            ['assigned male birth', 'male'],
            ['birthing people', 'pregnant women'],
            ['birthing person', 'pregnant woman'],
            ['gender', 'sex (where biological) / male and female participants'],
            ['gender affirm', '(reframe around specific clinical procedures)'],
            ['gender confirm', '(reframe around specific clinical procedures)'],
            ['gender confusion', '(omit; reframe clinically)'],
            ['gender difference', 'sex differences'],
            ['gender disparity', 'differences by sex'],
            ['gender dysphoria', '(use specific clinical diagnosis code/description)'],
            ['gender equit', 'fair treatment by sex'],
            ['gender expression', '(omit; reframe around observable behaviors)'],
            ['gender fluid', '(omit per administration two-sex policy)'],
            ['gender identity', '(omit per administration policy; use sex)'],
            ['gender minorit', '(omit; reframe around specific population)'],
            ['gender nonbinary', '(omit per administration policy)'],
            ['gender sex', 'sex'],
            ['genderqueer', '(omit per administration policy)'],
            ['lactating individual', 'lactating woman / nursing mother'],
            ['lactating people', 'lactating women / nursing mothers'],
            ['lactating person', 'lactating woman / nursing mother'],
            ['LGB', '(omit; reframe around specific health outcomes for the population studied)'],
            ['LGBT', '(omit; reframe around specific health outcomes for the population studied)'],
            ['neovagina', 'surgically constructed vagina / post-vaginoplasty anatomy'],
            ['phalloplasty', 'penile reconstruction surgery'],
            ['pregnant individual', 'pregnant woman'],
            ['pregnant people', 'pregnant women'],
            ['pregnant person', 'pregnant woman'],
            ['puberty blocker', 'gonadotropin-releasing hormone analog / pubertal suppression therapy'],
            ['queer', '(omit; reframe around specific population descriptor)'],
            ['sex assigned', 'sex / biological sex'],
            ['sex gender', 'sex'],
            ['sexual gender', '(omit; reframe around specific behavior or outcome)'],
            ['sexual gender minorit', '(omit; reframe around specific population)'],
            ['sexual minorit', '(omit; reframe around specific population studied)'],
            ['SGM', '(omit; spell out or reframe around specific population)'],
            ['transfeminine', '(omit; reframe around specific clinical population)'],
            ['transgender', '(omit per administration policy; use biological sex terminology)'],
            ['transmasculine', '(omit; reframe around specific clinical population)'],
            ['transphob', '(omit; reframe around measured attitudes/behaviors) (all forms)'],
            ['transsexual', '(omit; reframe around specific clinical population)'],
            ['vaginoplasty', 'vaginal reconstruction surgery'],

            // ===== Misinformation =====
            ['disinform', 'inaccurate information / false claims (measurable)'],
            ['malinform', 'misleading information / deceptive content'],
            ['misinform', 'inaccurate information / false claims (measurable)'],

            // ===== Vaccine Hesitancy =====
            ['accept vaccin', 'vaccine uptake / immunization acceptance'],
            ['acceptance vaccin', 'vaccine uptake / immunization acceptance'],
            ['refusal vaccin', 'vaccine non-uptake / delayed immunization'],
            ['refuse vaccin', 'decline immunization / vaccine non-uptake'],
            ['vaccination acceptance', 'immunization uptake'],
            ['vaccination hesitan', 'delayed immunization / vaccine attitudes'],
            ['vaccination refusal', 'immunization non-uptake'],
            ['vaccine acceptance', 'immunization uptake'],
            ['vaccine hesitan', 'immunization attitudes / delayed vaccination / vaccine uptake patterns (all forms)'],
            ['vaccine refusal', 'immunization non-uptake / delayed vaccination'],
        ];
    }
}
