import Alpine from 'alpinejs'

export default function RangeComponent() {
    Alpine.data('RangeComponent', (min, max, startMin, startMax, gap = 100) => ({
        min: Number(min),
        max: Number(max),
        minVal: Number(startMin) || Number(min),
        maxVal: Number(startMax) || Number(max),
        gap: Number(gap),

        // Высчитываем отступ слева в процентах
        get leftPercent() {
            return ((this.minVal - this.min) / (this.max - this.min)) * 100 + '%';
        },

        // Высчитываем отступ справа в процентах
        get rightPercent() {
            return (100 - ((this.maxVal - this.min) / (this.max - this.min)) * 100) + '%';
        },

        updateMin() {
            this.minVal = parseInt(this.minVal) || this.min;
            if (this.minVal < this.min) this.minVal = this.min;

            // Упираемся в правый ползунок с учетом gap
            if (this.maxVal - this.minVal < this.gap) {
                this.minVal = this.maxVal - this.gap;
            }
        },

        updateMax() {
            this.maxVal = parseInt(this.maxVal) || this.max;
            if (this.maxVal > this.max) this.maxVal = this.max;

            // Упираемся в левый ползунок с учетом gap
            if (this.maxVal - this.minVal < this.gap) {
                this.maxVal = this.minVal + this.gap;
            }
        }
    }));
}
