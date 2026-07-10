;(($) => {
  $(document).ready(() => {
    // Initialize all testimonial sliders on the page
    $(".alchemer-testimonial-slider").each(function () {
      initTestimonialSlider($(this))
    })
  })

  function initTestimonialSlider($slider) {
    // Get slider ID and settings
    var sliderId = $slider.data("slider-id")
    var centerMode = $slider.data("center-mode") === "true"
    var slidesToShow = Number.parseInt($slider.data("slides-to-show") || 3, 10)
    var $container = $slider.closest(".alchemer-multi-resort-reviews-testimonial")

    // Get slider elements
    var $track = $slider.find(".alchemer-testimonial-track")
    var $slides = $slider.find(".alchemer-testimonial-slide")
    var $dots = $(".alchemer-testimonial-dot[data-slider-id='" + sliderId + "']")
    var $prevBtn = $(".alchemer-testimonial-prev[data-slider-id='" + sliderId + "']")
    var $nextBtn = $(".alchemer-testimonial-next[data-slider-id='" + sliderId + "']")

    // Set up variables
    var slideCount = $slides.length
    var currentSlide = 0 // This is 0-based for JavaScript array indexing
    var autoplayTimer
    var maxSlideIndex = Math.max(0, slideCount - slidesToShow)

    // Don't initialize if there aren't enough slides
    if (slideCount === 0) {
      return
    }

    // Clone slides for true infinite loop if needed
    function setupInfiniteLoop() {
      // Only clone if we have enough slides
      if (slideCount > slidesToShow) {
        // Clone beginning slides and append to end
        $slides.slice(0, slidesToShow).clone().appendTo($track).addClass("cloned end-clone")

        // Clone end slides and prepend to beginning
        $slides.slice(-slidesToShow).clone().prependTo($track).addClass("cloned start-clone")

        // Update slides collection after cloning
        $slides = $slider.find(".alchemer-testimonial-slide")

        // Adjust initial position to show the original first slide
        $track.css("transform", "translateX(-" + (slidesToShow * 100) / slidesToShow + "%)")
      }
    }

    // Set up the slider
    function setupSlider() {
      // Set the width of each slide
      var slideWidth = 100 / slidesToShow
      $slides.css("width", slideWidth + "%")

      // Make sure the second slide has the active class initially
      $slides.removeClass("active center")
      $slides.eq(1).addClass("active center") // Second slide (index 1) gets active class

      // Show the first slide
      goToSlide(0, false)
    }

    // Function to update active slide - always the middle of visible slides
    function updateActiveSlide(index) {
      // Remove active class from all slides
      $slides.removeClass("active center")

      // The active slide should always be the middle of what's visible
      // For 3 slides showing, that's the current index + 1
      var activeIndex = index + 1 // This is the 0-based array index

      // Handle wrapping for the active index
      if (activeIndex >= slideCount) {
        activeIndex = activeIndex % slideCount
      }

      // Add active and center classes to the middle slide
      $slides.eq(activeIndex).addClass("active center")
    }

    // Function to go to a specific slide with true infinite loop
    function goToSlide(index, animate = true) {
      // Calculate the position
      var slideWidth = 100 / slidesToShow
      var position = -(index * slideWidth)

      // Apply the transition
      $track.css({
        transition: animate ? "transform 0.5s ease" : "none",
        transform: "translateX(" + position + "%)",
      })

      // Update current slide
      currentSlide = index

      // Handle wrapping for infinite rotation
      if (index < 0) {
        // If we've gone before the first slide, wait for animation to finish then jump to the end
        setTimeout(
          () => {
            $track.css({
              transition: "none",
              transform: "translateX(-" + maxSlideIndex * slideWidth + "%)",
            })
            currentSlide = maxSlideIndex
          },
          animate ? 500 : 0,
        )
      } else if (index > maxSlideIndex) {
        // If we've gone past the last slide, wait for animation to finish then jump to the beginning
        setTimeout(
          () => {
            $track.css({
              transition: "none",
              transform: "translateX(0%)",
            })
            currentSlide = 0
          },
          animate ? 500 : 0,
        )
      }

      // Update dots - note that dots are now 1-based
      $dots.removeClass("active")

      // Calculate the correct dot index, handling wrapping
      var dotIndex = index
      if (dotIndex < 0) dotIndex = maxSlideIndex
      if (dotIndex > maxSlideIndex) dotIndex = 0

      // We need to find the dot with data-slide equal to dotIndex+1
      $dots.filter('[data-slide="' + (dotIndex + 1) + '"]').addClass("active")

      // Update active slide
      updateActiveSlide(index)
    }

    // Initialize the slider
    setupSlider()

    // Initialize autoplay
    function startAutoplay() {
      autoplayTimer = setInterval(() => {
        var nextSlide = currentSlide + 1
        goToSlide(nextSlide)
      }, 5000)
    }

    function stopAutoplay() {
      clearInterval(autoplayTimer)
    }

    // Bind click events
    $prevBtn.on("click", (e) => {
      e.preventDefault()
      stopAutoplay()
      goToSlide(currentSlide - 1)
      startAutoplay()
    })

    $nextBtn.on("click", (e) => {
      e.preventDefault()
      stopAutoplay()
      goToSlide(currentSlide + 1)
      startAutoplay()
    })

    $dots.on("click", function (e) {
      e.preventDefault()
      stopAutoplay()
      // Convert 1-based data-slide to 0-based index for JavaScript
      var slideIndex = Number.parseInt($(this).data("slide"), 10) - 1
      goToSlide(slideIndex)
      startAutoplay()
    })

    // Start autoplay
    startAutoplay()

    // Pause autoplay on hover
    $slider.hover(
      () => {
        stopAutoplay()
      },
      () => {
        startAutoplay()
      },
    )
  }
})(jQuery)
