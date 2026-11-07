<div id="view-home" hidden class="max-w-[1500px] mx-auto pt-[22px] px-[41px] pb-[55px] max-compact:pt-[14px] max-compact:px-[15px] max-compact:pb-[42px]">
    <section id="featured-course" hidden class="mb-[31px] border border-[#502445] rounded-[13px] overflow-hidden bg-[linear-gradient(130deg,#1a0e25,#100819_62%,#210b20)]">
        <div class="flex items-center justify-between gap-4 py-5 px-6 max-compact:items-start max-compact:p-[15px] max-compact:flex-col">
            <div>
                <x-eyebrow color="text-accent-bright"><span class="inline-block w-[7px] h-[7px] rounded-[50%] bg-accent mr-[7px] shadow-glow"></span> ARTICULATE STORYLINE ԴԱՍԸՆԹԱՑ</x-eyebrow>
                <h1 id="featured-course-title" class="font-display font-bold text-[23px] my-[5px] max-compact:text-[20px]">ԴԱՍ 8</h1>
                <p class="text-[11px] text-prose">Դասընթացը՝ այս հարթակի ներսում</p>
            </div>
            <x-button variant="outline" id="featured-fullscreen" class="max-compact:self-stretch">Բացել մեծ էկրանով ↗</x-button>
        </div>
        <div class="h-[clamp(420px,69vh,790px)] bg-[#080510] max-compact:h-[clamp(310px,59vh,580px)]">
            <iframe id="featured-scorm-frame" title="Storyline հիմնական դասընթաց" allow="fullscreen; autoplay" allowfullscreen class="block align-baseline w-full h-full border-0 bg-[#111]"></iframe>
        </div>
    </section>

    <section class="mt-8 mb-[38px] max-compact:mt-[23px] max-compact:mb-7">
        <x-section-heading eyebrow="ՀԱՐՑՐԵՔ ԴԱՍԸՆԹԱՑԻ ՄԱՍԻՆ" title="Սովորեք հարցերի միջոցով" text="Տվեք հարց դասի նյութի մասին և ստացեք թեմային համապատասխան պատասխան։" />

        <aside id="assistant-panel" class="group/assistant flex flex-col w-full min-h-[390px] data-unavailable:min-h-0 border border-[#47224a] rounded-[11px] bg-[#12091d]">
            <div class="py-[17px] px-5 border-b border-[#382040] flex items-center justify-between max-compact:p-[13px]">
                <div class="flex gap-[9px] items-center">
                    <div class="w-7 h-7 grid place-items-center rounded-[8px] bg-[linear-gradient(135deg,#ff1744,#b60065)] shadow-[0_0_14px_#e5091455] text-white">✦</div>
                    <div>
                        <strong class="block text-[13px]">Ուսումնական օգնական</strong>
                        <small class="block text-[10px] text-[#a3a3a3] mt-1">Պատասխանում է դասանյութի հիման վրա</small>
                    </div>
                </div>
            </div>
            <div class="py-[10px] px-5 text-[#a3a3a3] text-[10px] border-b border-[#382040] group-data-unavailable/assistant:hidden"><span id="assistant-source">Հիմք՝ դասընթացի նյութեր</span> · Մնացած հարցեր՝ <span id="questions-remaining">{{ auth()->user()->aiQuestionsRemaining() }}</span>/{{ config('services.openai.question_limit') }}
            </div>
            <div id="chat-messages" class="group-data-unavailable/assistant:hidden flex-1 overflow-auto py-[18px] px-5 flex flex-col gap-[13px] min-h-[230px] bg-[#0e0818] max-compact:min-h-[205px] max-compact:p-3">
                <div class="text-[12px] leading-[1.75] py-[13px] px-[15px] rounded-[8px] max-w-[95%] whitespace-pre-wrap bg-[#25122f] text-ink self-start">Բարև։ Հարցրեք այս դասընթացի նյութի մասին։</div>
            </div>
            <div id="assistant-unavailable" hidden class="m-[18px] p-[10px] border border-[#4b2a51] bg-[#1c1026] rounded-[8px] text-[12px] max-compact:m-[10px]">Այս դասընթացի համար AI օգնականը դեռ հասանելի չէ։</div>
            <div id="questions-exhausted" hidden class="mx-[18px] mb-[6px] p-[10px] border border-[#4b2a51] bg-[#1c1026] rounded-[8px] text-[12px] max-compact:mx-[10px] max-compact:mb-[5px]">Դուք օգտագործել եք AI-ին հարց տալու ձեր բոլոր հնարավորությունները։</div>
            <form id="chat-form" hidden class="flex gap-[7px] items-end mx-[18px] mb-[6px] p-[10px] border border-[#4b2a51] bg-[#1c1026] rounded-[8px] max-compact:mx-[10px] max-compact:mb-[5px]">
                <textarea id="chat-input" rows="3" maxlength="{{ config('services.openai.question_max_length') }}" placeholder="Գրեք ձեր հարցը…" aria-label="Հարց գրեք" class="border-0 p-[2px] resize-none bg-transparent outline-0 focus-visible:outline-0 text-[#f5f5f1] text-[12px] leading-[1.6] flex-1 min-h-[48px] placeholder:text-[#8c8c8c]"></textarea>
                <span class="self-end text-[10px] text-[#a3a3a3] whitespace-nowrap"><span id="chat-count">0</span>/{{ config('services.openai.question_max_length') }}</span>
                <button id="chat-submit" type="submit" aria-label="Ուղարկել հարցը" class="w-9 h-9 py-px px-[6px] border-0 rounded-[6px] bg-[linear-gradient(135deg,#ff1744,#d80063)] shadow-[0_0_12px_#ff174433] text-white font-extrabold disabled:opacity-50">↑</button>
            </form>
            <div class="pt-[9px] px-5 pb-[13px] text-[#a3a3a3] text-[10px] group-data-unavailable/assistant:hidden">Հարցերը գրեք հայերենով։ Եթե պատասխանը նյութում չգտնվի, հարցը չի հաշվվում։</div>
        </aside>
    </section>

    <section id="open-question" hidden class="mt-8 mb-[38px] border border-line-strong rounded-[11px] bg-surface p-6 max-compact:mt-[23px] max-compact:mb-7 max-compact:p-4">
        <x-section-heading eyebrow="ԻՆՔՆՈՒՐՈՒՅՆ ՄՏԱԾՈՂՈՒԹՅՈՒՆ" title="Բաց հարց" text="Մտածեք կայուն զարգացման մասին՝ որպես մարդկանց կյանքի որակը, տնտեսությունն ու շրջակա միջավայրը միասին դիտարկող մոտեցում։" />

        <div class="border border-[#42264d] bg-raised rounded-[9px] p-5 mb-[14px] max-compact:p-[14px]">
            <strong class="block text-[13px] leading-[1.75] mb-[13px]">Ինչպե՞ս կարող են կայուն զարգացման ինդիկատորներն ու համաթվերը օգնել հասկանալ՝ արդյոք երկրի կամ համայնքի զարգացումը բարելավում է մարդկանց կյանքը՝ միաժամանակ պահպանելով տնտեսության և շրջակա միջավայրի հավասարակշռությունը։ Բերեք մեկ օրինակ և նշեք, թե ինչ տվյալներով կչափեիք առաջընթացը։</strong>
            <p class="text-[11px] leading-[1.7] my-[11px]">Պատասխանում բացատրեք գաղափարների կապը, բերեք հիմնավորված օրինակ և առաջարկեք չափելի տվյալ կամ ինդիկատոր։</p>
        </div>

        <textarea id="open-answer" placeholder="Գրեք ձեր հիմնավորված պատասխանը…" aria-label="Գրեք ձեր պատասխանը" class="block w-full min-h-[155px] border border-field-line rounded-[7px] bg-field text-ink p-3 resize-y text-[12px] leading-[1.65] focus:border-[#ff3678] focus:outline-[#ff3678]"></textarea>

        <div class="flex items-center justify-between gap-3 mt-3 mb-[13px] max-compact:items-start max-compact:flex-col">
            <span class="text-[10px] text-[#929e93] leading-[1.5]">Գնահատում՝ 100 միավորով։ Չափանիշներն ու աստիճանները՝ ստորև։</span>
            <x-button id="evaluate-answer" class="flex-none max-compact:w-full">Պատասխանել</x-button>
        </div>

        <details open class="my-[14px] border border-[#42264d] rounded-[8px] bg-raised py-3 px-[14px] text-[#d2dbd1]">
            <summary class="cursor-pointer text-[11px] font-bold text-accent-pale">Գնահատման սանդղակ և միավորների փոխարկում</summary>
            <div class="grid grid-cols-2 gap-2 mt-3">
                <x-rubric-band title="Օրինակելի · 90–100">Լիարժեք, ճշգրիտ և հիմնավորված պատասխան՝ հստակ օրինակով ու չափելի տվյալներով։</x-rubric-band>
                <x-rubric-band title="Իմացող / Լիարժեք · 75–89">Թեման լավ է բացատրված, հիմնական կապերն ու օրինակը ներկայացված են, կան փոքր բացթողումներ։</x-rubric-band>
                <x-rubric-band title="Զարգացող · 60–74">Կան թեմային վերաբերող գաղափարներ, բայց կապը, օրինակը կամ չափման եղանակը թերի է բացատրված։</x-rubric-band>
                <x-rubric-band title="Անբավարար · 0–59">Սպասելիքներից ցածր է․ պատասխանը թերի է, շեղված թեմայից կամ անճիշտ, և հիմնական պահանջվող տարրերը բացակայում են։</x-rubric-band>
            </div>
            <p class="text-[10px] leading-[1.65] text-[#b3b3b3] mt-[6px] pt-[10px] border-t border-[#2a332d]">Միավորներ՝ թեմայի ըմբռնում՝ 30, կայունության չափումների կապ՝ 25, օրինակ և չափում՝ 25, ամբողջականություն ու հստակություն՝ 20։ Միավորների գումարը որոշում է գնահատման աստիճանը։</p>
        </details>

        <div id="evaluation-result" aria-live="polite" class="empty:hidden"></div>
    </section>
</div>
