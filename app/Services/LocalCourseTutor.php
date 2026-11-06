<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Lesson;
use Illuminate\Support\Collection;

/**
 * Rule-based answers and grading used when no model is available. These are
 * prewritten demo responses based on keyword matching, not academic judgement.
 */
class LocalCourseTutor
{
    /** @param Collection<int, Goal> $goals */
    public function answer(string $question, Lesson $active, Collection $goals): array
    {
        $q = mb_strtolower($question);

        if (! preg_match('/կայուն|զարգաց|ինդիկատ|ցուցանիշ|համաթիվ|ինդեքս|մզհ|կզհ|կրթ|եկամուտ|հնա|հաե|աղքատ|սով|գենդեր|կլիմա|ջուր|էներգիա|քաղաք|գործընկեր|նպատակ|բնապահպան|սոցիալ|տնտես|էկոլոգ|co2|hdi|sdg|index|indicator|sustainable development|poverty|climate|education|income|gender|water|energy|goal|why|how|explain/iu', $q)) {
            return [
                'answer_armenian' => 'Ես նախագծված եմ օգնելու միայն այս դասընթացի շրջանակներում։ Խնդրում եմ հարցեր ուղղել կայուն զարգացման դասի նյութի վերաբերյալ։',
                'citations' => [],
                'suggested_followups_armenian' => ['Ի՞նչ է կայուն զարգացման ինդիկատորը։', 'Ի՞նչ է չափում մարդկային զարգացման համաթիվը։'],
            ];
        }

        if (preg_match('/ինդիկատոր|ցուցանիշ|indicator|չափելի|չափել/u', $q)) {
            $answer = 'Ըստ դասընթացի՝ կայուն զարգացման ինդիկատորը չափելի արժեք է, որը նկարագրում է տնտեսական, սոցիալական կամ էկոլոգիական ոլորտի վիճակը կամ փոփոխությունը։ Այն բարդ երևույթը դարձնում է համեմատելի և օգնում է հիմնավորել որոշումները։';
            $citations = [['source_type' => 'slide', 'reference_label' => 'Սլայդներ 3–4'], ['source_type' => 'literature', 'reference_label' => 'Թեմա 8 ընթերցանության նյութ']];
        } elseif (preg_match('/համաթիվ|ինդեքս|մզհ|կզհ|hdi|հնա|հաե|եկամուտ|index/u', $q)) {
            $answer = 'Ըստ դասընթացի՝ Մարդկային զարգացման համաթիվը համադրում է կյանքի սպասվող տևողության, կրթության և մեկ անձի հաշվով համախառն ազգային եկամտի չափումները։ Կայուն զարգացման համաթիվը լրացնում է պատկերը էկոլոգիական չափումներով, այդ թվում՝ մեկ շնչի հաշվով CO₂ արտանետումներով և նյութական ոտնահետքով։';
            $citations = [['source_type' => 'slide', 'reference_label' => 'Սլայդներ 5–7 և 10–11'], ['source_type' => 'literature', 'reference_label' => 'Թեմա 8 ընթերցանության նյութ']];
        } elseif (preg_match('/նպատակ|goal|աղքատ|սով|գենդեր|կլիմա|ջուր|էներգիա|քաղաք|գործընկեր|poverty|climate|water|energy/u', $q)) {
            $goal = $this->matchGoal($q, $goals);
            $answer = $goal
                ? "Ըստ դասընթացի՝ {$goal->number}-րդ նպատակը՝ «{$goal->title}», ուղղված է հետևյալ խնդրին․ {$goal->summary} Նպատակները փոխկապակցված են, ուստի դրանց առաջընթացը դիտարկվում է հարակից պայմանների հետ։"
                : 'Ըստ դասընթացի՝ ՄԱԿ-ի 17 նպատակները ներառում են աղքատության նվազեցում, առողջություն, կրթություն, հավասարություն, շրջակա միջավայր և համագործակցություն։ Դրանք փոխկապակցված են։';
            $citations = [['source_type' => 'slide', 'reference_label' => 'Սլայդներ 13–32'], ['source_type' => 'literature', 'reference_label' => 'Թեմա 8 ընթերցանության նյութ']];
        } else {
            $topic = mb_strtolower($active->title);
            $answer = "Ըստ այս բաժնի՝ {$topic} թեման կապում է կայուն զարգացման չափումները հասարակության և շրջակա միջավայրի խնդիրների հետ։ {$active->lead}";
            $citations = [['source_type' => $active->id === 'video' ? 'video' : 'literature', 'reference_label' => $active->label]];
        }

        return [
            'answer_armenian' => $answer,
            'citations' => $citations,
            'suggested_followups_armenian' => ['Ինչո՞վ են տարբերվում ինդիկատորն ու համաթիվը։', 'Ինչպե՞ս կարող է համայնքը ընտրել օգտակար ինդիկատոր։'],
        ];
    }

    public function evaluate(string $answer): array
    {
        $s = mb_strtolower($answer);
        $length = mb_strlen($s);

        $relevant = preg_match('/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|համաթվ|նպատակ|տնտես|շրջակա միջավայր/u', $s);
        $dimensions = count(array_filter([
            preg_match('/սոցիալ|հասարակ|առողջ|կրթ|հավասար/u', $s),
            preg_match('/տնտես|եկամուտ|աշխատ|աղքատ/u', $s),
            preg_match('/բնապահպան|էկոլոգ|շրջակա միջավայր|ռեսուրս|կլիմա/u', $s),
        ]));
        $example = preg_match('/օրինակ|համայնք|քաղաք|գյուղ|ջուր|էներգիա|տրանսպորտ|աղտոտ|թափոն|գործազրկ/u', $s);
        $measurable = preg_match('/ինդիկատոր|ցուցանիշ|համաթիվ|տոկոս|քանակ|չափել|հետևել|տվյալ|մակարդակ|բաժին/u', $s);
        $reasoned = preg_match('/քանի որ|հետևաբար|այսպիսով|որովհետև|կապված|ազդում/u', $s);
        $conceptHits = preg_match_all('/կայուն|զարգաց|ինդիկատոր|ցուցանիշ|համաթիվ|նպատակ/u', $s);

        $lengthPoints = match (true) {
            $length >= 450 => 12,
            $length >= 250 => 9,
            $length >= 120 => 6,
            $length >= 40 => 3,
            default => 0,
        };

        $criteria = [
            [
                'id' => 'understanding', 'title' => 'Թեմայի ըմբռնում', 'max' => 30,
                'points' => $relevant ? min(30, 18 + min(12, $conceptHits * 3)) : 0,
                'full' => 'Թեմայի հիմնական գաղափարները ճիշտ են կիրառված։',
                'partial' => 'Թեմային առնչվող միտք կա, բայց հիմնական հասկացությունները կարելի է ավելի հստակ բացատրել։',
                'missing' => 'Բացակայում է կայուն զարգացման թեմայի հստակ բացատրությունը։',
            ],
            [
                'id' => 'connections', 'title' => 'Կայունության չափումների կապ', 'max' => 25,
                'points' => [0, 10, 19, 25][$dimensions],
                'full' => 'Սոցիալական, տնտեսական և բնապահպանական կողմերի կապը հստակ է։',
                'partial' => 'Նշված է չափումներից մի քանիսը, սակայն դրանց կապը լիարժեք բացատրված չէ։',
                'missing' => 'Բացատրեք, թե ինչպես են սոցիալական, տնտեսական կամ բնապահպանական կողմերը կապվում թեմային։',
            ],
            [
                'id' => 'example_measure', 'title' => 'Օրինակ և չափում', 'max' => 25,
                'points' => $example && $measurable ? 25 : ($example || $measurable ? 13 : 0),
                'full' => 'Բերված է համապատասխան օրինակ և դրա առաջընթացը չափելու եղանակ։',
                'partial' => 'Օրինակը կամ չափման եղանակը կա, բայց երկուսն էլ միասին հստակ չեն։',
                'missing' => 'Բերեք կոնկրետ օրինակ և առաջարկեք չափելի տվյալ կամ ինդիկատոր։',
            ],
            [
                'id' => 'completeness', 'title' => 'Ամբողջականություն և հստակություն', 'max' => 20,
                'points' => min(20, $lengthPoints + ($reasoned ? 8 : 0)),
                'full' => 'Պատասխանը ամբողջական է, հստակ և հիմնավորում է գաղափարների կապը։',
                'partial' => 'Պատասխանը հասկանալի է, բայց հիմնավորման կամ ամբողջականության մի մասը պակասում է։',
                'missing' => 'Պատասխանը թերի է կամ դժվար է հասկանալ։',
            ],
        ];

        $breakdown = array_map(function (array $c) {
            $ratio = $c['points'] / $c['max'];

            return [
                'criterion_id' => $c['id'],
                'title_armenian' => $c['title'],
                'points_earned' => $c['points'],
                'max_points' => $c['max'],
                'reasoning_armenian' => $ratio >= .8 ? $c['full'] : ($ratio >= .4 ? $c['partial'] : $c['missing']),
            ];
        }, $criteria);

        $total = array_sum(array_column($breakdown, 'points_earned'));

        return [
            'total_score_awarded' => $total,
            'max_total_score' => 100,
            'grade_level' => match (true) {
                $total >= 90 => 'Օրինակելի',
                $total >= 75 => 'Իմացող / Լիարժեք',
                $total >= 60 => 'Զարգացող',
                default => 'Անբավարար',
            },
            'summary_feedback_armenian' => match (true) {
                $total < 60 => 'Սպասելիքներից ցածր է․ պատասխանը թերի է, շեղված թեմայից կամ անճիշտ, կամ բացակայում են պահանջվող հիմնական տարրերը։',
                $total < 75 => 'Պատասխանը մասամբ է բացահայտում թեման։ Ավելացրեք գաղափարների կապը, օրինակն ու չափելի հիմնավորումը։',
                $total < 90 => 'Պատասխանը լավ է ներկայացնում հիմնական գաղափարները, սակայն որոշ կապեր կամ հիմնավորումներ կարելի է լրացնել։',
                default => 'Պատասխանը ամբողջական ու հիմնավորված է և հստակ կապում է կայուն զարգացումը չափելի առաջընթացի հետ։',
            },
            'criterion_breakdown' => $breakdown,
            'actionable_tips_armenian' => $total < 60
                ? 'Վերանայեք թեմայի հիմնական գաղափարները, պատասխանեք հարցի բոլոր մասերին և բերեք համապատասխան օրինակ ու չափելի տվյալ։'
                : 'Ուժեղացրեք հիմնավորումը՝ բացատրելով, թե առաջարկված տվյալը ինչպես է ցույց տալիս կայուն զարգացման առաջընթացը։',
        ];
    }

    /** @param Collection<int, Goal> $goals */
    private function matchGoal(string $q, Collection $goals): ?Goal
    {
        $number = null;

        if (preg_match('/(?:նպատակ|goal|sdg|թիվ)\D{0,8}(1[0-7]|[1-9])|(?:^|\s)(1[0-7]|[1-9])(?:\s|$)/u', $q, $m)) {
            $number = (int) ($m[1] ?: ($m[2] ?? 0));
        }

        return $goals->first(function (Goal $goal) use ($q, $number) {
            $firstWord = explode(' ', mb_strtolower($goal->title))[0];

            return $goal->number === $number || str_contains($q, $firstWord);
        });
    }
}
