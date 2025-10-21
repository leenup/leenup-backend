<?php

namespace App\Story;

use App\Entity\Skill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use Zenstruck\Foundry\Story;

final class DefaultSkillsStory extends Story
{
    public function build(): void
    {
        // Récupérer les catégories pour associer les skills
        $devWeb = CategoryFactory::find(['title' => 'Développement Web']);
        $devMobile = CategoryFactory::find(['title' => 'Développement Mobile']);
        $design = CategoryFactory::find(['title' => 'Design & UX/UI']);
        $marketing = CategoryFactory::find(['title' => 'Marketing Digital']);
        $graphisme = CategoryFactory::find(['title' => 'Graphisme']);
        $noCode = CategoryFactory::find(['title' => 'No-Code / Low-Code']);
        $database = CategoryFactory::find(['title' => 'Bases de données']);
        $devops = CategoryFactory::find(['title' => 'DevOps & Cloud']);
        $cyber = CategoryFactory::find(['title' => 'Cybersécurité']);
        $ia = CategoryFactory::find(['title' => 'Intelligence Artificielle']);

        // Skills Développement Web
        if ($devWeb) {
            SkillFactory::createOne(['title' => 'HTML/CSS', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'JavaScript', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'TypeScript', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'React', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Vue.js', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Angular', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Next.js', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Nuxt.js', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'PHP', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Symfony', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Laravel', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'WordPress', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Node.js', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Express.js', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Tailwind CSS', 'category' => $devWeb]);
            SkillFactory::createOne(['title' => 'Bootstrap', 'category' => $devWeb]);
        }

        // Skills Développement Mobile
        if ($devMobile) {
            SkillFactory::createOne(['title' => 'React Native', 'category' => $devMobile]);
            SkillFactory::createOne(['title' => 'Flutter', 'category' => $devMobile]);
            SkillFactory::createOne(['title' => 'Swift (iOS)', 'category' => $devMobile]);
            SkillFactory::createOne(['title' => 'Kotlin (Android)', 'category' => $devMobile]);
            SkillFactory::createOne(['title' => 'Ionic', 'category' => $devMobile]);
        }

        // Skills Design & UX/UI
        if ($design) {
            SkillFactory::createOne(['title' => 'UX Design', 'category' => $design]);
            SkillFactory::createOne(['title' => 'UI Design', 'category' => $design]);
            SkillFactory::createOne(['title' => 'Design System', 'category' => $design]);
            SkillFactory::createOne(['title' => 'Wireframing', 'category' => $design]);
            SkillFactory::createOne(['title' => 'Prototypage', 'category' => $design]);
            SkillFactory::createOne(['title' => 'Recherche utilisateur', 'category' => $design]);
            SkillFactory::createOne(['title' => 'Architecture de l\'information', 'category' => $design]);
        }

        // Skills Marketing Digital
        if ($marketing) {
            SkillFactory::createOne(['title' => 'SEO', 'category' => $marketing]);
            SkillFactory::createOne(['title' => 'SEA (Google Ads)', 'category' => $marketing]);
            SkillFactory::createOne(['title' => 'Social Media Marketing', 'category' => $marketing]);
            SkillFactory::createOne(['title' => 'Email Marketing', 'category' => $marketing]);
            SkillFactory::createOne(['title' => 'Content Marketing', 'category' => $marketing]);
            SkillFactory::createOne(['title' => 'Google Analytics', 'category' => $marketing]);
            SkillFactory::createOne(['title' => 'Marketing Automation', 'category' => $marketing]);
        }

        // Skills Graphisme
        if ($graphisme) {
            SkillFactory::createOne(['title' => 'Photoshop', 'category' => $graphisme]);
            SkillFactory::createOne(['title' => 'Illustrator', 'category' => $graphisme]);
            SkillFactory::createOne(['title' => 'Figma', 'category' => $graphisme]);
            SkillFactory::createOne(['title' => 'Adobe XD', 'category' => $graphisme]);
            SkillFactory::createOne(['title' => 'Sketch', 'category' => $graphisme]);
            SkillFactory::createOne(['title' => 'InDesign', 'category' => $graphisme]);
            SkillFactory::createOne(['title' => 'Canva', 'category' => $graphisme]);
            SkillFactory::createOne(['title' => 'Procreate', 'category' => $graphisme]);
        }

        // Skills No-Code / Low-Code
        if ($noCode) {
            SkillFactory::createOne(['title' => 'Webflow', 'category' => $noCode]);
            SkillFactory::createOne(['title' => 'Bubble', 'category' => $noCode]);
            SkillFactory::createOne(['title' => 'Framer', 'category' => $noCode]);
            SkillFactory::createOne(['title' => 'Wix', 'category' => $noCode]);
            SkillFactory::createOne(['title' => 'Squarespace', 'category' => $noCode]);
            SkillFactory::createOne(['title' => 'Zapier', 'category' => $noCode]);
            SkillFactory::createOne(['title' => 'Make (Integromat)', 'category' => $noCode]);
        }

        // Skills Bases de données
        if ($database) {
            SkillFactory::createOne(['title' => 'MySQL', 'category' => $database]);
            SkillFactory::createOne(['title' => 'PostgreSQL', 'category' => $database]);
            SkillFactory::createOne(['title' => 'MongoDB', 'category' => $database]);
            SkillFactory::createOne(['title' => 'Redis', 'category' => $database]);
            SkillFactory::createOne(['title' => 'SQL', 'category' => $database]);
            SkillFactory::createOne(['title' => 'Firebase', 'category' => $database]);
        }

        // Skills DevOps & Cloud
        if ($devops) {
            SkillFactory::createOne(['title' => 'Docker', 'category' => $devops]);
            SkillFactory::createOne(['title' => 'Kubernetes', 'category' => $devops]);
            SkillFactory::createOne(['title' => 'AWS', 'category' => $devops]);
            SkillFactory::createOne(['title' => 'Azure', 'category' => $devops]);
            SkillFactory::createOne(['title' => 'Google Cloud', 'category' => $devops]);
            SkillFactory::createOne(['title' => 'CI/CD', 'category' => $devops]);
            SkillFactory::createOne(['title' => 'Git / GitHub', 'category' => $devops]);
        }

        // Skills Cybersécurité
        if ($cyber) {
            SkillFactory::createOne(['title' => 'Pentesting', 'category' => $cyber]);
            SkillFactory::createOne(['title' => 'Sécurité Web (OWASP)', 'category' => $cyber]);
            SkillFactory::createOne(['title' => 'Cryptographie', 'category' => $cyber]);
            SkillFactory::createOne(['title' => 'Audit de sécurité', 'category' => $cyber]);
        }

        // Skills Intelligence Artificielle
        if ($ia) {
            SkillFactory::createOne(['title' => 'Machine Learning', 'category' => $ia]);
            SkillFactory::createOne(['title' => 'Python (IA)', 'category' => $ia]);
            SkillFactory::createOne(['title' => 'TensorFlow', 'category' => $ia]);
            SkillFactory::createOne(['title' => 'PyTorch', 'category' => $ia]);
            SkillFactory::createOne(['title' => 'Prompt Engineering', 'category' => $ia]);
            SkillFactory::createOne(['title' => 'ChatGPT / LLMs', 'category' => $ia]);
        }

        echo "✅ " . SkillFactory::count() . " skills créées\n";
    }
}
