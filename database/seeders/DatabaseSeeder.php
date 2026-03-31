<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Experience;
use App\Models\Skill;
use App\Models\SocialLink;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::updateOrCreate(
            ['email' => 'fahmie@gmail.com'],
            [
                'name' => 'Fahmie Al Khudhorie',
                'password' => Hash::make('password'),
            ]
        );

        // Site Settings
        $settings = [
            // Hero
            ['key' => 'hero_label', 'value' => 'ESTABLISHED MMXXIV', 'group' => 'hero'],
            ['key' => 'hero_title_1', 'value' => 'Designing Space,', 'group' => 'hero'],
            ['key' => 'hero_title_2', 'value' => 'Defining', 'group' => 'hero'],
            ['key' => 'hero_title_highlight', 'value' => 'Stories.', 'group' => 'hero'],
            ['key' => 'hero_subtitle', 'value' => 'A multidisciplinary studio bridging the gap between digital precision and tactile architectural elegance. We build environments that breathe.', 'group' => 'hero'],

            // About
            ['key' => 'about_label', 'value' => 'The Philosophy', 'group' => 'about'],
            ['key' => 'about_title', 'value' => 'Every structure tells a tale. We are the authors of the physical realm.', 'group' => 'about'],
            ['key' => 'about_text_1', 'value' => 'Based in the intersection of traditional draftsmanship and modern generative design, my work focuses on the emotional resonance of space. I believe that a building is more than its materials—it is a vessel for experience.', 'group' => 'about'],
            ['key' => 'about_text_2', 'value' => "With a decade of experience in high-end residential and sustainable commercial projects, I've developed a signature style that prioritizes tonal depth and asymmetric balance, ensuring that every project is as unique as its inhabitant.", 'group' => 'about'],
            ['key' => 'about_portrait', 'value' => '', 'type' => 'image', 'group' => 'about'],

            // Stats
            ['key' => 'stat_1_value', 'value' => '120+', 'group' => 'stats'],
            ['key' => 'stat_1_label', 'value' => 'Projects Built', 'group' => 'stats'],
            ['key' => 'stat_2_value', 'value' => '15', 'group' => 'stats'],
            ['key' => 'stat_2_label', 'value' => 'Design Awards', 'group' => 'stats'],
            ['key' => 'stat_3_value', 'value' => '09', 'group' => 'stats'],
            ['key' => 'stat_3_label', 'value' => 'Global Cities', 'group' => 'stats'],

            // About Page
            ['key' => 'about_page_portrait', 'value' => '', 'type' => 'image', 'group' => 'about_page'],
            ['key' => 'about_page_intro', 'value' => 'I am a designer and architect of digital experiences based in San Francisco. My journey began at the intersection of structural engineering and visual arts, where I discovered that the most resilient systems are those built with human empathy at their core.', 'group' => 'about_page'],
            ['key' => 'about_page_bio', 'value' => "Over the last decade, I've partnered with forward-thinking brands to translate complex problems into elegant, editorial-grade digital solutions. I believe that every pixel should serve a purpose and every interaction should tell a story.", 'group' => 'about_page'],
            ['key' => 'about_page_years', 'value' => '12+', 'group' => 'about_page'],
            ['key' => 'about_page_projects', 'value' => '150+', 'group' => 'about_page'],

            // Contact
            ['key' => 'contact_address_1', 'value' => '1200 Architecture Lane', 'group' => 'contact'],
            ['key' => 'contact_address_2', 'value' => 'Design District', 'group' => 'contact'],
            ['key' => 'contact_address_3', 'value' => 'New York, NY 10012', 'group' => 'contact'],
            ['key' => 'contact_email', 'value' => 'hello@My Portofolio', 'group' => 'contact'],
            ['key' => 'contact_image', 'value' => '', 'type' => 'image', 'group' => 'contact'],
            ['key' => 'contact_availability', 'value' => 'Currently accepting new commissions for 2024.', 'group' => 'contact'],

            // General
            ['key' => 'site_name', 'value' => 'My Portofolio', 'group' => 'general'],
            ['key' => 'site_tagline', 'value' => 'Crafting meaningful architecture through editorial design and intentional minimalism.', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }

        // Projects
        $projects = [
            [
                'title' => 'The Glass Pavilion',
                'description' => 'A bespoke dashboard system designed for high-end real estate management, focusing on minimal visual noise and maximum functional clarity.',
                'category' => 'UI/UX Design',
                'year' => '2024',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuASJw3xBKqGBhAIQia4V6vOaLar5oGcj4uF9k6cLoMJA3EyKU-ytKXdV7QmInUJpqDM9dVd7uCy1Ecx29N50wQ5mJCIJvmEkwFrgf3D2MhEE85_qnF5qH6VI7u52Hx3BIQkNg7w2gU0Ehx8mnbfDewpLvXWAFN6d5j9xsMfwYxqXusJLCBO3lLzCQX46Widio5pHFR2g4fTplkEMv_D6GBpXvYG6kLLEe_3CMtibqWyOJHQypJlxF9z_LI94yqjkALVlL2nIT8vUiMz',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Concrete Minimalist',
                'description' => 'Visual identity and digital experience for an avant-garde interior design studio based in Copenhagen.',
                'category' => 'Branding',
                'year' => '2024',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuCXhg3SYS1HW4u6YDTVaHm0_Nj0RI_Md9uL_cxzDrFV6XqJSOmmJoYr3ddQnO2Gdzw08BQF1diSMObdpg5MGVKgBSMzFxd7qRcPDFviPui-iyYo7DXW9b_wAIGUkFEUCMeft4jcn8KycBt23i2wyN4779lyfWzsI71agcAizJJjXK2qnG1SGmBPOGANqBhz9OGy24VoRSW8_NQDGXb-lhp0g_lqBAJMhEc_16kRGC8WoDACGGUb9GBSnl0Uduynug08Y5htDG4hpBNO',
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Lumina Residence',
                'description' => 'A luxurious modern villa experience with warm interior lighting and deep architectural precision.',
                'category' => 'Architectural',
                'year' => '2022',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDu7rR5m7L7ZMTsR2oerQ9crR7YsZCaAi8Iq4lhn-0npykBjn4oK8Q6Ci3_F3uG8hAFk0pPNsjfsWlX5jnk8ybTk_VfReVhL6WXx0NWu3R1Xyh_RrLGoGfheipMsv6THC_cEJW16JZTAoNp2uOguaRwPWwD4zYBz0pHUGw1L5ViyEMIhSyQiJT_FIBL8xJSGMGGWeRI42dW8w8Cl47PCREkyg3RT7TX7tDJr50ZQQNKM3qV39hM08sbl9W1p3Ujdx9y-XUUM39RrJtA',
                'is_featured' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Core Engine v2',
                'description' => 'Building a lightning-fast headless CMS integration for global architectural firms.',
                'category' => 'Development',
                'year' => '2023',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuACQiTCxi2rNDf48AIh4RxXKtDKmkC6zXlE2mLPqGlaHCh2pT3Jt5YOrXA8oG342Tg3KsnudR4J7-bLairZSqC2EoGU551QRjDGFMttB0dX2ezI7wSNcYNhFqyBIu4dREjFEnf7silOecFI6le8DRi6h6xS8kKS--EnNVwMZ18K6i9mx4MQ6V6fQhH5zMvyzsm79qC_qkHsC_E169W9GbqP32yyCM_O5MqHc8ZIJHkmGAFpoJcUNoRDjr50Um3sDAiyf-e2CYrFI_ea',
                'is_featured' => false,
                'sort_order' => 4,
            ],
            [
                'title' => 'The Zenith Project',
                'description' => 'A spatial exploration of virtual reality galleries for digital artists and collectors.',
                'category' => 'Architectural',
                'year' => '2023',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDiuV78wDnUjWOIPJot2h1WTubvK07i_iX4OIteFzUkTtTPRxj3FNfLgEn0wRYmlinOsF-sQ6BRoo4W7RYahH0HsK8r4lnkWOHnpyo-tj0TZ-EDe9cf-oNauKGbusqfjmubu_RkFNgWDQNzhIEZchKvrJvTEvwytYXUMpc62yQHYE7RfUojXH3sZvwyfeK_Tya6h25k1Toyv2pPOY_5T8B8X6E39tSZqcpOq4viP1IeQ2mBIdG_XdJ26Z17EQCmcxl2fW-bUZU_nwcC',
                'is_featured' => false,
                'sort_order' => 5,
            ],
            [
                'title' => 'Neural Grid',
                'description' => 'Algorithmic layout generator for editorial-style web applications using advanced CSS grid.',
                'category' => 'Development',
                'year' => '2023',
                'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBlisDtsMgB_XkR57xe1CICXXmZOq-nW719OUrPh6fgZh6AEqvgzS5r8swAUGz8Sky5wKTwZlMhpsdgYEmUELDV3rw_19728C8LSjSUcWpXdMTDLyeLFsMywHXvwrXi3OTPxccYWwLDdbpK-eK0YQ0PAB-VJ5jO_TOXlWsgYsrL5zRffiWXJoZViPocV8DPXVUtOxhkI0vM0oEPmITSjSWmooE_iSvuJv0XhrXbnf8rZ0FxFUuzDTjemebTs2Y-biI1ziu405nfV60F',
                'is_featured' => false,
                'sort_order' => 6,
            ],
        ];

        foreach ($projects as $project) {
            Project::updateOrCreate(['title' => $project['title']], $project);
        }

        // Experiences
        $experiences = [
            [
                'title' => 'Lead Experience Designer',
                'company' => 'Vanguard Creative Agency',
                'description' => 'Spearheading digital transformation for Fortune 500 clients. Led the redesign of an enterprise SaaS platform resulting in a 40% increase in user retention.',
                'icon' => 'token',
                'color' => 'primary',
                'start_year' => '2021',
                'end_year' => 'PRESENT',
                'sort_order' => 1,
            ],
            [
                'title' => 'Senior Product Designer',
                'company' => 'Lumina Systems',
                'description' => 'Developed and maintained a comprehensive design system used by 5 product teams. Reduced engineering handoff time by 30% through improved documentation.',
                'icon' => 'blur_on',
                'color' => 'secondary',
                'start_year' => '2018',
                'end_year' => '2021',
                'sort_order' => 2,
            ],
            [
                'title' => 'BFA in Digital Architecture',
                'company' => 'Rhode Island School of Design',
                'description' => 'Focused on computational design and spatial interfaces. Graduated with honors and received the Faculty Award for Innovation.',
                'icon' => 'school',
                'color' => 'tertiary',
                'start_year' => '2014',
                'end_year' => '2018',
                'sort_order' => 3,
            ],
        ];

        foreach ($experiences as $exp) {
            Experience::updateOrCreate(['title' => $exp['title']], $exp);
        }

        // Skills
        $skills = [
            ['title' => 'Systems Architecture', 'description' => 'Designing scalable design systems that bridge the gap between creative vision and technical execution.', 'icon' => 'architecture', 'color' => 'primary', 'is_wide' => true, 'sort_order' => 1],
            ['title' => 'UI Design', 'description' => 'High-fidelity interfaces with editorial layouts and micro-interactions.', 'icon' => 'auto_awesome', 'color' => 'tertiary', 'is_wide' => false, 'sort_order' => 2],
            ['title' => 'Frontend', 'description' => 'Crafting responsive, performant code using React, Tailwind, and Framer.', 'icon' => 'terminal', 'color' => 'secondary', 'is_wide' => false, 'sort_order' => 3],
            ['title' => 'Strategy', 'description' => 'Defining product roadmaps and user-centric problem solving frameworks.', 'icon' => 'psychology', 'color' => 'primary', 'is_wide' => false, 'sort_order' => 4],
        ];

        foreach ($skills as $skill) {
            Skill::updateOrCreate(['title' => $skill['title']], $skill);
        }

        // Social Links
        $socials = [
            ['platform' => 'LinkedIn', 'url' => '#', 'sort_order' => 1],
            ['platform' => 'GitHub', 'url' => '#', 'sort_order' => 2],
            ['platform' => 'Dribbble', 'url' => '#', 'sort_order' => 3],
            ['platform' => 'Instagram', 'url' => '#', 'sort_order' => 4],
        ];

        foreach ($socials as $social) {
            SocialLink::updateOrCreate(['platform' => $social['platform']], $social);
        }
    }
}
