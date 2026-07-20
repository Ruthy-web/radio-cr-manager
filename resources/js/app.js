import './bootstrap';
import Alpine from 'alpinejs';

/**
 * Composant Alpine du formulaire de compte rendu : sélection en cascade
 * hôpital -> examen, avec pré-remplissage du contenu médical depuis le
 * template choisi. Défini ici (fichier servi depuis 'self') plutôt qu'en
 * <script> inline pour rester compatible avec la CSP stricte (F9).
 */
window.reportForm = function (initial) {
    return {
        examsByHospital: initial.examsByHospital,
        hospitalId: initial.hospitalId,
        examId: initial.examId,
        heading: initial.heading,
        indication: initial.indication,
        technique: initial.technique,
        resultsText: initial.resultsText,
        conclusion: initial.conclusion,
        currentExams() {
            return this.examsByHospital[this.hospitalId] || [];
        },
        onHospitalChange() {
            this.examId = null;
        },
        requiresSide() {
            const exam = this.currentExams().find((e) => e.id === this.examId);

            return exam ? exam.requires_side : false;
        },
        onExamChange() {
            const exam = this.currentExams().find((e) => e.id === this.examId);
            if (!exam) return;
            this.heading = exam.heading || '';
            this.indication = exam.indication || '';
            this.technique = exam.technique || '';
            this.resultsText = exam.results || '';
            this.conclusion = exam.conclusion || '';
        },
    };
};

window.Alpine = Alpine;
Alpine.start();
