# Title: Jekyll Lightbox tag
# Authors: vlamy (vlamy.fr)
#
# Description: Display images in Jekyll, using lightbox
# javascript (http://lokeshdhakar.com/projects/lightbox2/)
#
# Download: https://github.com/vlamy/jekyll-lightbox-tag
# Documentation: https://github.com/vlamy/jekyll-lightbox-tag
#
# Syntax: {% lightbox preset img.jpg [group:"group"] [caption:"title"] [alt="value"] %}
# Example: {% lightbox preset img.jpg group:"test_group" caption:"test image title" alt="test image" %}
#
# Licence : BSD new ()
#
# Inspired from : Rob Wierzbowski’s Jekyll Image Tag
# (https://github.com/robwierzbowski/jekyll-image-tag)
#
# See the documentation for full configuration and usage instructions.

require 'fileutils'
require 'pathname'
require 'digest/md5'
require 'mini_magick'

module Jekyll

  class Lightbox < Liquid::Tag

    def initialize(tag_name, markup, tokens)
      @markup = markup
      super
    end

    def render(context)

      # Render any liquid variables in tag arguments and unescape template code
      render_markup = Liquid::Template.parse(@markup).render(context).gsub(/\\\{\\\{|\\\{\\%/, '\{\{' => '{{', '\{\%' => '{%')

      # Gather settings
      site = context.registers[:site]
      settings = site.config['lightbox']
      markup = /^(?:(?<preset>[^\s.:\/]+)\s+)?(?<image_src>[^\s]+\.[a-zA-Z0-9]{3,4})*(?<group>\sgroup:"[^"]+")*(?<caption>\scaption:"[^"]+")*(?<html_attr>[\s\S]+)?$/.match(render_markup)
      #markup.names.each do |name|
      # puts "#{name} --> #{markup[name]}"
      #end
      preset = settings['presets'][ markup[:preset] ]
      caption = markup['caption'][9..markup['caption'].length - 1] if markup['caption']
      group = markup['group'][7..markup['group'].length - 1] if markup['group']

      raise "Lightbox Tag can't read this tag. Try {% lightbox ... %}." unless markup

      # Assign defaults
      settings['source'] ||= 'img'
      settings['output'] ||= 'generated'

      # Prevent Jekyll from erasing our generated files
      site.config['keep_files'] << settings['output'] unless site.config['keep_files'].include?(settings['output'])

      # Process instance
      instance = if preset
        {
          :width => preset['width'],
          :height => preset['height'],
          :src => markup[:image_src]
        }
      elsif dim = /^(?:(?<width>\d+)|auto)(?:x)(?:(?<height>\d+)|auto)$/i.match(markup[:preset])
        {
          :width => dim['width'],
          :height => dim['height'],
          :src => markup[:image_src]
        }
      else
        { :src => markup[:image_src] }
      end

      # Process html attributes
      html_attr = if markup[:html_attr]
        Hash[ *markup[:html_attr].scan(/(?<attr>[^\s="]+)(?:="(?<value>[^"]+)")?\s?/).flatten ]
      else
        {}
      end

      if preset && preset['attr']
        html_attr = preset['attr'].merge(html_attr)
      end

      html_attr_string = html_attr.inject('') { |string, attrs|
        if attrs[1]
          string << "#{attrs[0]}=\"#{attrs[1]}\" "
        else
          string << "#{attrs[0]} "
        end
      }

      # Raise some exceptions before we start expensive processing
      raise "Lightbox Tag can't find the \"#{markup[:preset]}\" preset. Check lightbox: presets in _config.yml for a list of presets." unless preset || dim || markup[:preset].nil?

      # Generate resized images
      generated_thumb = generate_image(instance, site.source, site.dest, settings['source'], settings['output'])

      html_markup = "<a href=\"#{settings['source']}/#{instance[:src]}\" "
      html_markup << "title=#{caption} " if caption
      if group
       html_markup << "data-lightbox=#{group} "
      else
       html_markup << "data-lightbox=\"default\" "
      end

      html_markup << "><img src=\"#{generated_thumb}\" #{html_attr_string}></a>"

      # Return the markup!
      html_markup
    end

    def generate_image(instance, site_source, site_dest, image_source, image_dest)

      image = MiniMagick::Image.open(File.join(site_source, image_source, instance[:src]))
      digest = Digest::MD5.hexdigest(image.to_blob).slice!(0..5)

      image_dir = File.dirname(instance[:src])
      ext = File.extname(instance[:src])
      basename = File.basename(instance[:src], ext)

      orig_width = image[:width].to_f
      orig_height = image[:height].to_f
      orig_ratio = orig_width/orig_height

      gen_width = if instance[:width]
        instance[:width].to_f
      elsif instance[:height]
        orig_ratio * instance[:height].to_f
      else
        orig_width
      end
      gen_height = if instance[:height]
        instance[:height].to_f
      elsif instance[:width]
        instance[:width].to_f / orig_ratio
      else
        orig_height
      end
      gen_ratio = gen_width/gen_height

      # Don't allow upscaling. If the image is smaller than the requested dimensions, recalculate.
      if orig_width < gen_width || orig_height < gen_height
        undersize = true
        gen_width = if orig_ratio < gen_ratio then orig_width else orig_height * gen_ratio end
        gen_height = if orig_ratio > gen_ratio then orig_height else orig_width/gen_ratio end
      end

      gen_name = "#{basename}-#{gen_width.round}x#{gen_height.round}-#{digest}#{ext}"
      gen_dest_dir = File.join(site_dest, image_dest, image_dir)
      gen_dest_file = File.join(gen_dest_dir, gen_name)

      # Generate resized files
      unless File.exists?(gen_dest_file)

        warn "Warning:".yellow + " #{instance[:src]} is smaller than the requested output file. It will be resized without upscaling." if undersize

        # If the destination directory doesn't exist, create it
        FileUtils.mkdir_p(gen_dest_dir) unless File.exist?(gen_dest_dir)

        # Let people know their images are being generated
        puts "Generating #{gen_name}"

        # Scale and crop
        image.combine_options do |i|
          i.resize "#{gen_width}x#{gen_height}^"
          i.gravity "center"
          i.crop "#{gen_width}x#{gen_height}+0+0"
        end

        image.write gen_dest_file
      end

      # Return path relative to the site root for html
      Pathname.new(File.join('/', image_dest, image_dir, gen_name)).cleanpath
    end
  end
end

Liquid::Template.register_tag('lightbox', Jekyll::Lightbox)