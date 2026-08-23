import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "border border-[hsl(42_56%_40%)] bg-gradient-to-b from-[hsl(42_62%_44%)] via-[hsl(38_56%_30%)] to-[hsl(34_50%_22%)] text-[hsl(46_92%_90%)] tracking-wide shadow-[inset_0_1px_0_hsl(46_82%_72%/0.38),0_4px_12px_-6px_hsl(0_0%_0%/0.7)] hover:from-[hsl(44_66%_48%)] hover:to-[hsl(36_52%_24%)] hover:shadow-[inset_0_1px_0_hsl(46_86%_76%/0.48),0_0_16px_hsl(42_72%_46%/0.45)]",
        destructive:
          "border border-destructive/60 bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60",
        outline:
          "border border-[hsl(40_32%_36%)] bg-gradient-to-b from-[hsl(30_13%_16%)] to-[hsl(28_14%_10%)] text-[hsl(42_56%_70%)] shadow-xs hover:border-[hsl(42_56%_50%)] hover:text-[hsl(46_72%_80%)] hover:shadow-[0_0_14px_hsl(42_62%_42%/0.3)]",
        secondary:
          "border border-border/60 bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost:
          "hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-9 px-4 py-2 has-[>svg]:px-3",
        "sm": "h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",
        "lg": "h-10 rounded-md px-6 has-[>svg]:px-4",
        "icon": "size-9",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
