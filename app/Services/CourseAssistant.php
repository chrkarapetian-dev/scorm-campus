<?php

namespace App\Services;

use App\Exceptions\OpenAiRequestFailed;
use App\Models\LearningCourse;
use App\Models\Lesson;
use Illuminate\Support\Facades\Log;

/** Answers questions and grades open answers, with the model or, failing that, LocalCourseTutor. */
class CourseAssistant
{
    public const OPEN_QUESTION = 'Ինչպե՞ս կարող են կայուն զարգացման ինդիկատորներն ու համաթվերը օգնել հասկանալ՝ արդյոք երկրի կամ համայնքի զարգացումը բարելավում է մարդկանց կյանքը՝ պահպանելով տնտեսության ու շրջակա միջավայրի հավասարակշռությունը։ Բերեք մեկ օրինակ և նշեք՝ ինչ տվյալներով կչափեիք առաջընթացը։';

    private const QA_INSTRUCTIONS = 'Դու հայերեն ուսումնական զրուցակից և մտածողության խթանող ես։ Պատասխանիր բնական, իմաստալից և բավարար խորությամբ. մի սահմանափակվիր նախապես գրված տարբերակներով կամ կարճ բառարանային սահմանումներով։ Հարցի էությունը պարզելուց հետո զարգացրու պատասխանը՝ անհրաժեշտության դեպքում կապելով սոցիալական, տնտեսական և բնապահպանական կողմերը, ներկայացնելով պատճառահետևանքային կապեր, տարբեր տեսակետներ, փոխզիջումներ կամ առօրյա/համայնքային օրինակներ։ Կարող ես քննարկել կայուն զարգացման թեմային առնչվող ավելի լայն գաղափարներ՝ դասանյութից դուրս ընդհանուր գիտելիքի հիման վրա. հստակ տարբերակիր դասանյութում նշվածը քո ընդհանուր բացատրությունից։ Օգտագործիր դասանյութը որպես ելակետ և մի պնդիր, թե այն ասում է բան, որը այնտեղ չկա։ Եթե հարցը թեմային առնչվող մտահղացում, քննադատություն կամ կիրառություն է, զարգացրու այն՝ ոչ թե մերժիր որպես դասընթացից դուրս։ Ճշգրիտ ընթացիկ վիճակագրություն կամ հղում մի հորինիր։ Վերջում կարող ես տալ առավելագույնը երկու իրական, բաց հարց՝ որպես կամընտիր շարունակություն, ոչ թե որպես պատասխանի փոխարինում։ Պատասխանիր հայերեն։ Վերադարձիր միայն պահանջվող JSON-ը։';

    private const EVALUATION_INSTRUCTIONS = 'Դու հայերեն ակադեմիական գնահատող ես։ Գնահատիր միայն սովորողի պատասխանում առկա ապացույցները՝ տրված դասանյութի և չափանիշների համեմատ։ Յուրաքանչյուր միավոր չի կարող գերազանցել չափանիշի առավելագույնը։ Ընդհանուր միավորը չափանիշների միավորների գումարն է՝ առավելագույնը 100։ Անավարտ, թեմայից շեղված կամ հիմնական պահանջվող տարրերից զուրկ պատասխանը պետք է ստանա 60-ից ցածր միավոր։ Անբավարար՝ 0–59, Զարգացող՝ 60–74, Իմացող / Լիարժեք՝ 75–89, Օրինակելի՝ 90–100։ Տուր կարճ հայերեն հիմնավորում յուրաքանչյուր չափանիշի համար։ Մի հորինիր սովորողի մտադրությունը։ Վերադարձիր միայն պահանջվող JSON-ը։';

    private const RUBRIC = [
        ['criterion_id' => 'understanding', 'max_points' => 30, 'description' => 'Թեմայի ճիշտ ըմբռնումը և հիմնական հասկացությունների օգտագործումը։'],
        ['criterion_id' => 'connections', 'max_points' => 25, 'description' => 'Սոցիալական, տնտեսական և բնապահպանական չափումների կապը։'],
        ['criterion_id' => 'example_measure', 'max_points' => 25, 'description' => 'Հիմնավորված օրինակ և չափելի տվյալ կամ ինդիկատոր։'],
        ['criterion_id' => 'completeness', 'max_points' => 20, 'description' => 'Հարցի բոլոր մասերին հստակ և ամբողջական պատասխան։'],
    ];

    private const NO_KEY_NOTE = 'Նշում․ ցուցադրվում է դասընթացի ներկառուցված պատասխան։ Լայն, ազատ AI պատասխանների համար պետք է միացված լինի OpenAI API բանալին։';

    private const FALLBACK_NOTE = 'Նշում․ այս պահին ցուցադրվում է դասընթացի ներկառուցված պատասխանը։';

    private const EVALUATION_FALLBACK_NOTE = 'AI գնահատումն այս պահին հասանելի չէ։ Ստորև ցուցադրվում է չափանիշների վրա հիմնված տեղային գնահատում։';

    public function __construct(
        private readonly OpenAiResponses $openAi,
        private readonly LocalCourseTutor $tutor,
    ) {}

    public function answer(string $question, ?string $activeSection): array
    {
        $course = LearningCourse::main();
        $active = $course->lessons->firstWhere('id', $activeSection) ?? $course->lessons[1];

        if (! $this->openAi->configured()) {
            return $this->tutor->answer($question, $active, $course->goals) + ['notice' => self::NO_KEY_NOTE];
        }

        try {
            return $this->openAi->json(self::qaFormat(), self::QA_INSTRUCTIONS, [
                'course_material' => $this->courseMaterial($course),
                'active_section' => $active->label,
                'student_question' => $question,
            ]);
        } catch (OpenAiRequestFailed $e) {
            Log::error('AI Q&A error: '.($e->errorCode ?? $e->getMessage()));

            return $this->tutor->answer($question, $active, $course->goals) + [
                'notice' => $this->explain($e).' '.self::FALLBACK_NOTE,
                'ai_failed' => true,
            ];
        }
    }

    public function evaluate(string $answer): array
    {
        if (! $this->openAi->configured()) {
            return $this->tutor->evaluate($answer) + ['notice' => self::EVALUATION_FALLBACK_NOTE];
        }

        try {
            $result = $this->openAi->json(self::evaluationFormat(), self::EVALUATION_INSTRUCTIONS, [
                'course_material' => $this->courseMaterial(LearningCourse::main()),
                'question_prompt' => self::OPEN_QUESTION,
                'rubric' => self::RUBRIC,
                'student_response' => $answer,
            ]);

            return $this->withTrustedScores($result);
        } catch (OpenAiRequestFailed $e) {
            Log::error('AI evaluation error: '.($e->errorCode ?? $e->getMessage()));

            return $this->tutor->evaluate($answer) + ['notice' => self::EVALUATION_FALLBACK_NOTE];
        }
    }

    /** The model's arithmetic isn't trusted: points are clamped to the rubric and re-summed. */
    private function withTrustedScores(array $result): array
    {
        $max = array_column(self::RUBRIC, 'max_points', 'criterion_id');
        $total = 0;

        foreach ($result['criterion_breakdown'] ?? [] as $i => $row) {
            if (isset($max[$row['criterion_id'] ?? null])) {
                $row['max_points'] = $max[$row['criterion_id']];
                $row['points_earned'] = max(0, min($row['max_points'], (float) ($row['points_earned'] ?? 0)));
                $result['criterion_breakdown'][$i] = $row;
            }

            $total += $row['points_earned'] ?? 0;
        }

        $result['max_total_score'] = 100;
        $result['total_score_awarded'] = $total;

        return $result;
    }

    private function explain(OpenAiRequestFailed $e): string
    {
        return match ($e->errorCode) {
            'insufficient_quota' => 'OpenAI API-ի օգտագործման հասանելի վարկը սպառվել է կամ API հաշվարկային կարգավորումը դեռ ակտիվ չէ։ Ստուգեք API billing-ը։',
            'invalid_api_key' => 'OpenAI API բանալին անվավեր է։ Ստեղծեք նոր բանալի և պահեք այն սերվերի .env ֆայլում։',
            default => 'AI ծառայությունը ժամանակավորապես անհասանելի է։ Փորձեք կրկին։',
        };
    }

    private function courseMaterial(LearningCourse $course): string
    {
        return $course->lessons
            ->map(fn (Lesson $lesson) => "{$lesson->label} — {$lesson->title}\n{$lesson->lead}\n{$lesson->plainText()}")
            ->implode("\n\n");
    }

    private static function qaFormat(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'armenian_course_answer',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['answer_armenian', 'citations', 'suggested_followups_armenian'],
                'properties' => [
                    'answer_armenian' => ['type' => 'string'],
                    'citations' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['source_type', 'reference_label'],
                        'properties' => [
                            'source_type' => ['type' => 'string', 'enum' => ['slide', 'video', 'literature']],
                            'reference_label' => ['type' => 'string'],
                        ],
                    ]],
                    'suggested_followups_armenian' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ];
    }

    private static function evaluationFormat(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'armenian_rubric_evaluation',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['total_score_awarded', 'max_total_score', 'summary_feedback_armenian', 'criterion_breakdown', 'actionable_tips_armenian'],
                'properties' => [
                    'total_score_awarded' => ['type' => 'number'],
                    'max_total_score' => ['type' => 'number'],
                    'summary_feedback_armenian' => ['type' => 'string'],
                    'actionable_tips_armenian' => ['type' => 'string'],
                    'criterion_breakdown' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['criterion_id', 'points_earned', 'max_points', 'reasoning_armenian'],
                        'properties' => [
                            'criterion_id' => ['type' => 'string'],
                            'points_earned' => ['type' => 'number'],
                            'max_points' => ['type' => 'number'],
                            'reasoning_armenian' => ['type' => 'string'],
                        ],
                    ]],
                ],
            ],
        ];
    }
}
